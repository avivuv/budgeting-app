<?php

use App\DTOs\Receipt\ParsedReceiptData;
use App\Enums\FamilyRole;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionSource;
use App\Exceptions\Receipt\ReceiptParserException;
use App\Jobs\ProcessReceiptJob;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Receipt\Parsers\FakeReceiptParser;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use App\Services\Receipt\ReceiptService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Scan → draft → review → Simpan through the web (docs/06). The fake parser
| stands in for the AI; everything else (storage, quota, queue job, rules,
| transactions) is real.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Storage::fake(config('filesystems.uploads'));

    $this->owner = User::factory()->create();
    $this->family = createFamily($this->owner);
    $this->parser = new FakeReceiptParser;
    app()->instance(ReceiptParserInterface::class, $this->parser);

    $this->wallet = Wallet::query()->firstOrFail();
    $this->groceries = Category::query()->where('name', 'Belanja Harian')->firstOrFail();
    $this->food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();

    $this->actingAs($this->owner);
});

/**
 * A real JPEG with random pixels, so every call is a different file.
 */
function receiptImage(string $name = 'struk.jpg', int $width = 600, int $height = 900): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    for ($i = 0; $i < 200; $i++) {
        imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), random_int(0, 0xFFFFFF));
    }

    $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
    imagejpeg($image, $path);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}

function receiptPdf(int $pages = 1): UploadedFile
{
    $objects = '';
    for ($i = 0; $i < $pages; $i++) {
        $objects .= ($i + 3)." 0 obj << /Type /Page /Parent 2 0 R >> endobj\n";
    }

    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Count {$pages} >> endobj\n{$objects}%%EOF ".random_int(1, PHP_INT_MAX));

    return new UploadedFile($path, 'invoice.pdf', 'application/pdf', null, true);
}

function reading(array $overrides = []): ParsedReceiptData
{
    $output = [
        'is_receipt' => true,
        'document_type' => 'receipt',
        'merchant' => 'Indomaret',
        'date' => '2026-10-30',
        'items' => [
            ['name' => 'Susu UHT 1L', 'qty' => 2, 'unit_price' => 18_500, 'total' => 37_000, 'category' => 'Belanja Harian'],
            ['name' => 'Roti Tawar', 'qty' => 1, 'unit_price' => 15_500, 'total' => 15_500, 'category' => 'Belanja Harian'],
            ['name' => 'Keripik', 'qty' => 1, 'unit_price' => 32_500, 'total' => 32_500, 'category' => 'Makanan & Minuman'],
        ],
        'discount' => 0,
        'tax' => 2_500,
        'total' => 87_500,
        'suggested_category' => 'Belanja Harian',
        'confidence' => 0.92,
        ...$overrides,
    ];

    return ParsedReceiptData::fromOutput($output, 'fake', $output);
}

function upload(UploadedFile $file): Receipt
{
    test()->post('/receipts', ['file' => $file])->assertRedirect();

    return Receipt::query()->latest('id')->firstOrFail();
}

it('reads an upload into a draft without creating any transaction [BR-H08]', function () {
    $this->parser->respondWith(reading());

    $receipt = upload(receiptImage());

    expect($receipt->status)->toBe(ReceiptStatus::Parsed)
        ->and($receipt->merchant)->toBe('Indomaret')
        ->and($receipt->total)->toBe(87_500)
        ->and($receipt->receipt_date->toDateString())->toBe('2026-10-30')
        ->and($receipt->suggested_category_id)->toBe($this->groceries->id)
        ->and($receipt->suggested_wallet_id)->toBe($this->wallet->id)
        ->and($receipt->flags)->toBe([])
        ->and($receipt->expires_at->toDateTimeString())->toBe('2026-11-01 12:00:00')
        ->and($receipt->items)->toHaveCount(3)
        ->and($receipt->items[2]->category_id)->toBe($this->food->id)
        // Kept untouched for audit (BR-H09).
        ->and($receipt->raw_response['total'])->toBe(87_500);

    // Nothing is a transaction yet: no balance, report or budget changes.
    expect(Transaction::query()->count())->toBe(0);
    Storage::disk(config('filesystems.uploads'))->assertExists($receipt->file_path);

    $this->get("/receipts/{$receipt->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipts/Show')
            ->where('receipt.status', 'parsed')
            ->where('receipt.total', 87_500)
            ->where('receipt.itemsTotal', 85_000)
            ->has('receipt.items', 3)
            ->has('categories')
            ->has('wallets'));
});

it('saves one expense of the paid total with source receipt [BR-H01] [BR-H07]', function () {
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());

    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'total'])->assertRedirect('/transactions');
    // Pressing Simpan again changes nothing (BR-H07, BR-B13).
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'total'])->assertRedirect('/transactions');

    $transactions = Transaction::query()->get();
    expect($transactions)->toHaveCount(1)
        ->and($transactions[0]->amount)->toBe(87_500)
        ->and($transactions[0]->date->toDateString())->toBe('2026-10-30')
        ->and($transactions[0]->category_id)->toBe($this->groceries->id)
        ->and($transactions[0]->wallet_id)->toBe($this->wallet->id)
        ->and($transactions[0]->source)->toBe(TransactionSource::Receipt)
        ->and($transactions[0]->receipt_id)->toBe($receipt->id)
        ->and($transactions[0]->merchant)->toBe('Indomaret')
        ->and($transactions[0]->created_by)->toBe($this->owner->id)
        ->and($receipt->refresh()->status)->toBe(ReceiptStatus::Confirmed);
});

it('splits per item category so the transactions add up to the receipt total [BR-H02] [INV-09]', function () {
    // Items 85.000 + tax 2.500: groceries 52.500, snacks 32.500.
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());

    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'split'])->assertRedirect('/transactions');

    $byCategory = Transaction::query()->pluck('amount', 'category_id')->all();

    // 87.500 × 52.500/85.000 = 54.044,1 → 54.044; 87.500 × 32.500/85.000 = 33.455,8 → 33.455; +1 to the largest.
    expect($byCategory)->toEqual([$this->groceries->id => 54_045, $this->food->id => 33_455])
        ->and(array_sum($byCategory))->toBe($receipt->refresh()->total);
});

it('saves the user corrections and keeps the AI reading for audit [BR-H09]', function () {
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());

    $this->put("/receipts/{$receipt->id}", [
        'merchant' => 'Indomaret Sudirman',
        'receipt_date' => '2026-10-29',
        'total' => '90rb',
        'category_id' => $this->food->id,
        'wallet_id' => $this->wallet->id,
        'items' => [
            ['name' => 'Susu UHT 1L', 'qty' => 2, 'total' => '37rb', 'category_id' => $this->groceries->id],
            ['name' => 'Kopi', 'qty' => 1, 'total' => 53_000, 'category_id' => null],
        ],
    ])->assertRedirect("/receipts/{$receipt->id}")->assertSessionHasNoErrors();

    $receipt->refresh();
    expect($receipt->merchant)->toBe('Indomaret Sudirman')
        ->and($receipt->total)->toBe(90_000)
        ->and($receipt->receipt_date->toDateString())->toBe('2026-10-29')
        ->and($receipt->suggested_category_id)->toBe($this->food->id)
        ->and($receipt->items->pluck('total')->all())->toBe([37_000, 53_000])
        ->and($receipt->raw_response['merchant'])->toBe('Indomaret')
        ->and($receipt->raw_response['total'])->toBe(87_500)
        ->and(Transaction::query()->count())->toBe(0);

    // Split: the item without category follows the receipt category (food).
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'split']);

    expect(Transaction::query()->pluck('amount', 'category_id')->all())
        ->toEqual([$this->groceries->id => 37_000, $this->food->id => 53_000]);
});

it('records corrections in the activity log [BR-B14]', function () {
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());

    $this->put("/receipts/{$receipt->id}", ['receipt_date' => '2026-10-30', 'total' => 88_000, 'category_id' => $this->groceries->id, 'wallet_id' => $this->wallet->id]);

    $log = Activity::query()->where('subject_type', Receipt::class)->where('subject_id', $receipt->id)->where('event', 'updated')->latest('id')->firstOrFail();

    expect($log->attribute_changes['attributes']['total'])->toBe(88_000)
        ->and($log->attribute_changes['old']['total'])->toBe(87_500)
        ->and($log->attribute_changes['attributes'])->not->toHaveKey('raw_response');
});

it('warns about a mismatching total and lowers confidence, but keeps the total [BR-H03]', function () {
    $this->parser->respondWith(reading(['tax' => 0, 'total' => 95_000]));

    $receipt = upload(receiptImage());

    expect($receipt->flags)->toBe([Receipt::FLAG_TOTAL_MISMATCH])
        ->and($receipt->confidence)->toBe(0.46)
        ->and($receipt->total)->toBe(95_000);
});

it('uses today for an unreadable or implausible date and says so [BR-H06]', function (?string $date) {
    $this->parser->respondWith(reading(['date' => $date]));

    $receipt = upload(receiptImage());

    expect($receipt->receipt_date->toDateString())->toBe('2026-10-31')
        ->and($receipt->hasFlag(Receipt::FLAG_DATE_REPLACED))->toBeTrue();
})->with([null, '2026-11-05', '2024-01-01']);

it('rejects the same file twice but only warns about a similar receipt [BR-H04]', function () {
    $this->parser->respondWith(reading(), reading());
    $file = receiptImage();
    $copy = new UploadedFile(tap(tempnam(sys_get_temp_dir(), 'cp'), fn ($p) => copy($file->getRealPath(), $p)), 'lagi.jpg', 'image/jpeg', null, true);

    upload($file);
    $this->post('/receipts', ['file' => $copy])->assertSessionHasErrors(['file' => 'File struk yang sama sudah pernah diunggah.']);
    expect(Receipt::query()->count())->toBe(1);

    // Another photo of the same purchase: allowed, with a warning.
    $second = upload(receiptImage());
    expect($second->hasFlag(Receipt::FLAG_POSSIBLE_DUPLICATE))->toBeTrue();

    // A rejected upload may be sent again.
    $first = Receipt::query()->oldest('id')->firstOrFail();
    $this->delete("/receipts/{$first->id}")->assertRedirect('/receipts');
    $this->parser->respondWith(reading());
    $again = new UploadedFile(tap(tempnam(sys_get_temp_dir(), 'cp'), fn ($p) => copy($copy->getRealPath(), $p)), 'lagi.jpg', 'image/jpeg', null, true);
    $this->post('/receipts', ['file' => $again])->assertSessionHasNoErrors();
});

it('refuses unsupported files before the quota is used [BR-H11]', function () {
    $text = UploadedFile::fake()->createWithContent('catatan.txt', 'belanja 50rb');
    $this->post('/receipts', ['file' => $text])->assertSessionHasErrors('file');

    $this->post('/receipts', ['file' => receiptPdf(4)])->assertSessionHasErrors(['file' => 'PDF maksimal 3 halaman.']);

    config(['receipt.max_file_mb' => 0]);
    $this->post('/receipts', ['file' => receiptImage()])->assertSessionHasErrors('file');

    expect(Receipt::query()->count())->toBe(0)
        ->and(UsageLog::query()->sum('count'))->toBe(0);
});

it('accepts PDF invoices as they are and shrinks large photos [BR-H11]', function () {
    $this->parser->respondWith(reading(['document_type' => 'invoice']), reading(['merchant' => 'Tokopedia']));

    $pdf = upload(receiptPdf(3));
    expect($pdf->mime_type)->toBe('application/pdf')
        ->and($pdf->file_path)->toEndWith('.pdf')
        ->and($pdf->file_path)->toStartWith("receipts/{$this->family->id}/2026/10/");

    $photo = upload(receiptImage('besar.png', 3000, 4000));
    $stored = Storage::disk(config('filesystems.uploads'))->get($photo->file_path);
    [$width, $height] = getimagesizefromstring($stored);

    expect($photo->mime_type)->toBe('image/jpeg')
        ->and(max($width, $height))->toBe(1600)
        ->and($this->parser->calls()[1]['mime'])->toBe('image/jpeg');
});

it('counts scans against the monthly quota and refunds only system failures [BR-H05]', function () {
    $this->family->forceFill(['receipt_quota_monthly' => 2])->save();

    // "Not a receipt" uses a scan.
    $this->parser->respondWith(ParsedReceiptData::notAReceipt('fake'));
    $notReceipt = upload(receiptImage());
    expect($notReceipt->status)->toBe(ReceiptStatus::Failed)
        ->and($notReceipt->error)->toBe('Ini sepertinya bukan struk atau bukti transaksi.');

    // A provider failure after all tries gives the scan back.
    Queue::fake();
    $failed = upload(receiptImage());
    expect(app(ReceiptService::class)->quota()['used'])->toBe(2);
    (new ProcessReceiptJob($this->family->id, $failed->id))->failed(new ReceiptParserException('Gemini HTTP 500'));
    expect($failed->refresh()->status)->toBe(ReceiptStatus::Failed)
        ->and(app(ReceiptService::class)->quota())->toBe(['used' => 1, 'limit' => 2, 'remaining' => 1]);

    upload(receiptImage());
    $this->post('/receipts', ['file' => receiptImage()])
        ->assertSessionHasErrors(['file' => 'Kuota scan struk bulan ini (2) sudah habis. Catat transaksi secara manual.']);

    $this->get('/receipts')->assertInertia(fn (Assert $page) => $page
        ->component('receipts/Index')
        ->where('quota', ['used' => 2, 'limit' => 2, 'remaining' => 0])
        ->has('receipts', 3));
});

it('lets the job throw so the queue retries a provider error', function () {
    Queue::fake();
    $receipt = upload(receiptImage());
    Queue::assertPushed(ProcessReceiptJob::class, fn (ProcessReceiptJob $job) => $job->receiptId === $receipt->id && $job->familyId === $this->family->id);

    $this->parser->respondWith(new ReceiptParserException('timeout'));
    expect(fn () => app()->call([new ProcessReceiptJob($this->family->id, $receipt->id), 'handle']))->toThrow(ReceiptParserException::class);

    // The next try succeeds.
    $this->parser->respondWith(reading());
    app()->call([new ProcessReceiptJob($this->family->id, $receipt->id), 'handle']);
    expect($receipt->refresh()->status)->toBe(ReceiptStatus::Parsed);
});

it('expires drafts after 24 hours and deletes stale files after 7 days [BR-H10]', function () {
    $this->parser->respondWith(reading(), reading(['merchant' => 'Alfamart']));
    $draft = upload(receiptImage());
    $rejected = upload(receiptImage());
    $this->delete("/receipts/{$rejected->id}");

    $this->travelTo('2026-11-01 12:30:00');
    $this->artisan('receipts:expire')->assertSuccessful();

    expect($draft->refresh()->status)->toBe(ReceiptStatus::Expired)
        ->and($draft->file_path)->not->toBeNull();
    $this->post("/receipts/{$draft->id}/confirm", ['mode' => 'total'])->assertSessionHasErrors('status');
    expect(Transaction::query()->count())->toBe(0);

    $this->travelTo('2026-11-08 13:00:00');
    $path = $draft->file_path;
    $this->artisan('receipts:expire')->assertSuccessful();

    expect($draft->refresh()->file_path)->toBeNull()
        ->and($rejected->refresh()->file_path)->toBeNull();
    Storage::disk(config('filesystems.uploads'))->assertMissing($path);
});

it('cannot edit or confirm without the required choices [BR-H08] [BR-H09]', function () {
    $this->parser->respondWith(reading(['suggested_category' => 'Tidak ada', 'items' => []]));
    $receipt = upload(receiptImage());
    expect($receipt->suggested_category_id)->toBeNull();

    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'total'])->assertSessionHasErrors(['category_id' => 'Pilih kategori sebelum menyimpan.']);
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'apa'])->assertSessionHasErrors('mode');

    $income = Category::query()->where('type', 'income')->firstOrFail();
    $this->put("/receipts/{$receipt->id}", ['receipt_date' => '2026-10-30', 'total' => 1_000, 'category_id' => $income->id])->assertSessionHasErrors('category_id');

    $this->put("/receipts/{$receipt->id}", ['receipt_date' => '2026-10-30', 'total' => 87_500, 'category_id' => $this->groceries->id, 'wallet_id' => $this->wallet->id]);
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'split'])->assertSessionHasErrors(['mode' => 'Struk ini tidak punya item untuk dipecah.']);
    expect(Transaction::query()->count())->toBe(0);
});

it('keeps receipts and their images inside the family [BR-I06]', function () {
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());
    $this->get("/receipts/{$receipt->id}/image")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    $stranger = User::factory()->create();
    createFamily($stranger, 'Keluarga Lain');
    $this->actingAs($stranger);

    $this->get("/receipts/{$receipt->id}")->assertNotFound();
    $this->get("/receipts/{$receipt->id}/image")->assertNotFound();
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'total'])->assertNotFound();
    $this->put("/receipts/{$receipt->id}", ['receipt_date' => '2026-10-30', 'total' => 1])->assertNotFound();

    // Never reachable as a public file.
    expect($this->get('/storage/'.$receipt->file_path)->status())->toBeIn([403, 404]);
});

it('lets viewers look but not scan or confirm [BR-B09]', function () {
    $this->parser->respondWith(reading());
    $receipt = upload(receiptImage());

    $viewer = addMember($this->family, FamilyRole::Viewer);
    $this->actingAs($viewer);

    $this->get("/receipts/{$receipt->id}")->assertOk();
    $this->post('/receipts', ['file' => receiptImage()])->assertForbidden();
    $this->post("/receipts/{$receipt->id}/confirm", ['mode' => 'total'])->assertForbidden();
    $this->delete("/receipts/{$receipt->id}")->assertForbidden();
});
