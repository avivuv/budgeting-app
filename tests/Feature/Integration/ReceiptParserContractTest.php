<?php

use App\DTOs\Receipt\ParsedReceiptData;
use App\DTOs\Receipt\ReceiptParseContextData;
use App\Enums\ReceiptDocumentType;
use App\Exceptions\Receipt\ReceiptParserException;
use App\Services\Receipt\Parsers\FakeReceiptParser;
use App\Services\Receipt\Parsers\GeminiReceiptParser;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| Every receipt parser must give the same shape for the same file (docs/06 §4).
| Provider APIs are faked; the fake parser answers with its built-in sample.
*/

function contractContext(): ReceiptParseContextData
{
    return new ReceiptParseContextData(
        categories: ['Makanan & Minuman', 'Belanja Harian', 'Transportasi'],
        wallets: ['Tunai', 'GoPay'],
        today: '2026-10-31',
    );
}

function contractFile(): string
{
    $path = tempnam(sys_get_temp_dir(), 'ctr').'.jpg';
    imagejpeg(imagecreatetruecolor(20, 20), $path);

    return $path;
}

function geminiAnswer(array $output, int $status = 200): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($output)]]]]],
            'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 180],
            'modelVersion' => 'gemini-2.5-flash',
        ], $status),
    ]);
}

dataset('parsers', [
    'fake' => fn (): ReceiptParserInterface => new FakeReceiptParser,
    'gemini' => function (): ReceiptParserInterface {
        geminiAnswer(FakeReceiptParser::sample(contractContext()));

        return new GeminiReceiptParser(apiKey: 'test-key', model: 'gemini-2.5-flash');
    },
]);

it('returns a complete reading in whole rupiah with family names', function (Closure $make) {
    $reading = $make()->parse(contractFile(), 'image/jpeg', contractContext());

    expect($reading)->toBeInstanceOf(ParsedReceiptData::class)
        ->and($reading->isReceipt)->toBeTrue()
        ->and($reading->documentType)->toBe(ReceiptDocumentType::Receipt)
        ->and($reading->merchant)->toBe('Indomaret')
        ->and($reading->date)->toBe('2026-10-31')
        ->and($reading->total)->toBeInt()->toBeGreaterThan(0)
        ->and($reading->items)->not->toBeEmpty()
        ->and($reading->model)->not->toBeNull()
        ->and($reading->raw)->not->toBeEmpty();

    foreach ($reading->items as $item) {
        expect($item->total)->toBeInt()
            ->and($item->category)->toBeIn(contractContext()->categories);
    }

    expect(array_sum(array_map(fn ($item) => $item->total, $reading->items)) - $reading->discount + $reading->tax)->toBe($reading->total);
})->with('parsers');

it('sends the file inline with the prompt and the response schema', function () {
    geminiAnswer(FakeReceiptParser::sample(contractContext()));

    (new GeminiReceiptParser(apiKey: 'test-key', model: 'gemini-2.5-flash'))->parse(contractFile(), 'image/jpeg', contractContext());

    Http::assertSent(function (Request $request): bool {
        $parts = $request['contents'][0]['parts'];

        return str_ends_with($request->url(), '/models/gemini-2.5-flash:generateContent')
            && $request->header('x-goog-api-key') === ['test-key']
            && str_contains($parts[0]['text'], 'Makanan & Minuman, Belanja Harian, Transportasi')
            && str_contains($parts[0]['text'], 'Tunai, GoPay')
            && $parts[1]['inline_data']['mime_type'] === 'image/jpeg'
            && base64_decode($parts[1]['inline_data']['data']) !== ''
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && $request['generationConfig']['responseSchema']['properties']['total']['type'] === 'INTEGER';
    });
});

it('says when the file is not a receipt', function () {
    geminiAnswer(['is_receipt' => false, 'items' => [], 'total' => null, 'confidence' => 0.1]);

    $reading = (new GeminiReceiptParser(apiKey: 'k', model: 'm'))->parse(contractFile(), 'image/jpeg', contractContext());

    expect($reading->isReceipt)->toBeFalse()->and($reading->model)->toBe('m');
});

it('turns provider failures into retryable parser errors', function (int $status, bool $rateLimited) {
    geminiAnswer([], $status);

    try {
        (new GeminiReceiptParser(apiKey: 'k', model: 'm'))->parse(contractFile(), 'image/jpeg', contractContext());
        $this->fail('expected an exception');
    } catch (ReceiptParserException $e) {
        expect($e->rateLimited)->toBe($rateLimited);
    }
})->with([[429, true], [500, false], [400, false]]);

it('fails on an answer without JSON', function () {
    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'maaf']]]]]])]);

    (new GeminiReceiptParser(apiKey: 'k', model: 'm'))->parse(contractFile(), 'image/jpeg', contractContext());
})->throws(ReceiptParserException::class);

it('asks the fallback model when the first one is unsure', function () {
    $unsure = [...FakeReceiptParser::sample(contractContext()), 'confidence' => 0.3, 'merchant' => 'Indomrt'];
    $sure = [...FakeReceiptParser::sample(contractContext()), 'confidence' => 0.95];

    Http::fake([
        '*/models/flash:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($unsure)]]]]]]),
        '*/models/pro:generateContent' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($sure)]]]]]]),
    ]);

    $reading = (new GeminiReceiptParser(apiKey: 'k', model: 'flash', fallbackModel: 'pro'))->parse(contractFile(), 'image/jpeg', contractContext());

    expect($reading->merchant)->toBe('Indomaret')->and($reading->model)->toBe('pro');
});

it('is the configured parser', function () {
    config(['receipt.driver' => 'gemini', 'receipt.gemini.api_key' => 'k']);
    app()->forgetInstance(ReceiptParserInterface::class);

    expect(app(ReceiptParserInterface::class))->toBeInstanceOf(GeminiReceiptParser::class);

    config(['receipt.driver' => 'fake']);
    app()->forgetInstance(ReceiptParserInterface::class);

    expect(app(ReceiptParserInterface::class))->toBeInstanceOf(FakeReceiptParser::class);
});
