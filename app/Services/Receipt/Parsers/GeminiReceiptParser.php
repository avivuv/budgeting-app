<?php

declare(strict_types=1);

namespace App\Services\Receipt\Parsers;

use App\DTOs\Receipt\ParsedReceiptData;
use App\DTOs\Receipt\ReceiptParseContextData;
use App\Exceptions\Receipt\ReceiptParserException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (docs/06 §5.1): the file is sent inline with the prompt and a
 * response schema, so the answer is JSON in the shape of §5.4.
 */
class GeminiReceiptParser implements ReceiptParserInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $fallbackModel = null,
        private readonly float $fallbackBelow = 0.6,
        private readonly string $endpoint = 'https://generativelanguage.googleapis.com/v1beta',
        private readonly int $timeout = 30,
    ) {}

    public function parse(string $filePath, string $mimeType, ReceiptParseContextData $context): ParsedReceiptData
    {
        $file = base64_encode((string) file_get_contents($filePath));
        $result = $this->ask($this->model, $file, $mimeType, $context);

        // Optional second opinion from a stronger model when unsure (docs/06 §5.3).
        if ($this->fallbackModel !== null && $result->isReceipt && ($result->confidence ?? 0.0) < $this->fallbackBelow) {
            $second = $this->ask($this->fallbackModel, $file, $mimeType, $context);

            if ($second->isReceipt && ($second->confidence ?? 0.0) >= ($result->confidence ?? 0.0)) {
                return $second;
            }
        }

        return $result;
    }

    private function ask(string $model, string $file, string $mimeType, ReceiptParseContextData $context): ParsedReceiptData
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['x-goog-api-key' => $this->apiKey])
                ->post("{$this->endpoint}/models/{$model}:generateContent", [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [
                            ['text' => self::prompt($context)],
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => $file]],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => self::schema(),
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new ReceiptParserException("Gemini unreachable: {$e->getMessage()}");
        }

        if ($response->status() === 429) {
            throw ReceiptParserException::rateLimited('Gemini');
        }

        if ($response->failed()) {
            throw new ReceiptParserException("Gemini HTTP {$response->status()}: ".mb_substr($response->body(), 0, 500));
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $text = data_get($body, 'candidates.0.content.parts.0.text');
        $output = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($output)) {
            throw new ReceiptParserException('Gemini returned no JSON reading');
        }

        $raw = ['output' => $output, 'usage' => data_get($body, 'usageMetadata'), 'model_version' => data_get($body, 'modelVersion')];

        return ParsedReceiptData::fromOutput($output, $model, $raw);
    }

    public static function prompt(ReceiptParseContextData $context): string
    {
        $template = (string) file_get_contents(resource_path('prompts/receipt.md'));

        return strtr($template, [
            '{{today}}' => $context->today,
            '{{categories}}' => $context->categories === [] ? '(none)' : implode(', ', $context->categories),
            '{{wallets}}' => $context->wallets === [] ? '(none)' : implode(', ', $context->wallets),
        ]);
    }

    /**
     * docs/06 §5.4 as a Gemini response schema.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $nullableString = ['type' => 'STRING', 'nullable' => true];
        $nullableInteger = ['type' => 'INTEGER', 'nullable' => true];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'is_receipt' => ['type' => 'BOOLEAN'],
                'document_type' => ['type' => 'STRING', 'enum' => ['receipt', 'transfer_proof', 'ewallet', 'invoice'], 'nullable' => true],
                'merchant' => $nullableString,
                'date' => $nullableString,
                'currency' => $nullableString,
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'qty' => ['type' => 'NUMBER'],
                            'unit_price' => $nullableInteger,
                            'total' => ['type' => 'INTEGER'],
                            'category' => $nullableString,
                        ],
                        'required' => ['name', 'qty', 'total'],
                    ],
                ],
                'subtotal' => $nullableInteger,
                'discount' => $nullableInteger,
                'tax' => $nullableInteger,
                'total' => $nullableInteger,
                'payment_method' => $nullableString,
                'suggested_wallet' => $nullableString,
                'suggested_category' => $nullableString,
                'confidence' => ['type' => 'NUMBER'],
            ],
            'required' => ['is_receipt', 'items', 'total', 'confidence'],
        ];
    }
}
