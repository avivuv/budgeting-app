<?php

use App\DTOs\Receipt\ParsedReceiptData;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Receipt\Parsers\FakeReceiptParser;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| API round-trip for receipts (docs/13 §3): upload → GET draft → PATCH
| corrections → confirm → the transactions read back through the API match
| the corrected draft, and balances follow.
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

    Sanctum::actingAs($this->owner, ['read', 'write']);
});

function apiReceiptImage(): UploadedFile
{
    $image = imagecreatetruecolor(400, 700);
    imagesetpixel($image, random_int(0, 399), random_int(0, 699), random_int(1, 0xFFFFFF));
    imagestring($image, 5, 10, 10, (string) random_int(1, PHP_INT_MAX), 0xFFFFFF);
    $path = tempnam(sys_get_temp_dir(), 'api').'.jpg';
    imagejpeg($image, $path);

    return new UploadedFile($path, 'struk.jpg', 'image/jpeg', null, true);
}

it('round-trips a receipt from upload to transactions [BR-H01] [BR-H08] [BR-H09] [BR-H02] [INV-09]', function () {
    $output = [
        'is_receipt' => true,
        'document_type' => 'receipt',
        'merchant' => 'Alfamart',
        'date' => '2026-10-31',
        'items' => [
            ['name' => 'Beras 5kg', 'qty' => 1, 'unit_price' => 75_000, 'total' => 75_000, 'category' => 'Belanja Harian'],
            ['name' => 'Teh Botol', 'qty' => 3, 'unit_price' => 5_000, 'total' => 15_000, 'category' => 'Makanan & Minuman'],
        ],
        'discount' => 3_000,
        'tax' => 0,
        'total' => 87_000,
        'suggested_category' => 'Belanja Harian',
        'confidence' => 0.88,
    ];
    $this->parser->respondWith(ParsedReceiptData::fromOutput($output, 'fake', $output));

    $balanceBefore = collect($this->getJson('/api/v1/wallets')->json('data'))->firstWhere('id', $this->wallet->id)['balance'];

    $id = $this->post('/api/v1/receipts', ['file' => apiReceiptImage()], ['Accept' => 'application/json'])
        ->assertStatus(202)
        ->assertJsonPath('meta.quota.used', 1)
        ->json('data.id');

    // Draft: read, nothing booked yet (BR-H08).
    $this->getJson("/api/v1/receipts/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'parsed')
        ->assertJsonPath('data.merchant', 'Alfamart')
        ->assertJsonPath('data.total', 87_000)
        ->assertJsonPath('data.discount', 3_000)
        ->assertJsonPath('data.category_id', $this->groceries->id)
        ->assertJsonPath('data.wallet_id', $this->wallet->id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.1.category_id', $this->food->id)
        ->assertJsonPath('data.transactions', []);
    $this->getJson('/api/v1/transactions')->assertJsonPath('meta.totals.expense', 0);

    // Correct the draft (BR-H09): one more item, total 99.000.
    $this->patchJson("/api/v1/receipts/{$id}", [
        'merchant' => 'Alfamart Kemang',
        'receipt_date' => '2026-10-30',
        'total' => '99rb',
        'category_id' => $this->groceries->id,
        'wallet_id' => $this->wallet->id,
        'items' => [
            ['name' => 'Beras 5kg', 'qty' => 1, 'total' => 75_000, 'category_id' => $this->groceries->id],
            ['name' => 'Teh Botol', 'qty' => 3, 'total' => 15_000, 'category_id' => $this->food->id],
            ['name' => 'Roti', 'qty' => 1, 'total' => '12rb', 'category_id' => $this->food->id],
        ],
    ])->assertOk()
        ->assertJsonPath('data.total', 99_000)
        ->assertJsonPath('data.merchant', 'Alfamart Kemang')
        ->assertJsonCount(3, 'data.items');

    $confirmed = $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'split'], ['Idempotency-Key' => 'a3a1e0c2-1111-4c55-9a51-0b2b6a6f0001'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->json('data.transactions');

    // Items 102.000 → 99.000 paid: groceries 75.000 × 99/102 = 72.794,1; food 27.000 × 99/102 = 26.205,8 (+1 leftover to groceries).
    $amounts = collect($confirmed)->pluck('amount', 'category.id')->all();
    expect($amounts)->toEqual([$this->groceries->id => 72_795, $this->food->id => 26_205])
        ->and(array_sum($amounts))->toBe(99_000);

    // Read back through the transaction API: same numbers, source receipt, corrected date.
    $listed = $this->getJson('/api/v1/transactions?source=receipt')->assertOk();
    expect($listed->json('meta.totals.expense'))->toBe(99_000)
        ->and(collect($listed->json('data'))->pluck('date')->unique()->all())->toBe(['2026-10-30'])
        ->and(collect($listed->json('data'))->pluck('note')->unique()->all())->toBe(['Alfamart Kemang']);

    $balanceAfter = collect($this->getJson('/api/v1/wallets')->json('data'))->firstWhere('id', $this->wallet->id)['balance'];
    expect($balanceBefore - $balanceAfter)->toBe(99_000);

    // Confirming again returns the same receipt and books nothing more (BR-H07).
    $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])->assertCreated()->assertJsonCount(2, 'data.transactions');
    $this->getJson('/api/v1/transactions?source=receipt')->assertJsonPath('meta.totals.expense', 99_000);
});

it('answers 429 when the monthly quota is used up [BR-H05]', function () {
    $this->family->forceFill(['receipt_quota_monthly' => 1])->save();

    $this->post('/api/v1/receipts', ['file' => apiReceiptImage()], ['Accept' => 'application/json'])->assertStatus(202);
    $this->post('/api/v1/receipts', ['file' => apiReceiptImage()], ['Accept' => 'application/json'])
        ->assertStatus(429)
        ->assertJsonPath('errors.file.0', 'Kuota scan struk bulan ini (1) sudah habis. Catat transaksi secara manual.');
});

it('validates uploads and corrections like the web [BR-H11] [BR-H09]', function () {
    $this->post('/api/v1/receipts', [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
    $this->post('/api/v1/receipts', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'halo')], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'Format file tidak didukung. Kirim foto (JPG, PNG, WEBP, HEIC) atau PDF.');

    $id = $this->post('/api/v1/receipts', ['file' => apiReceiptImage()], ['Accept' => 'application/json'])->json('data.id');

    $this->patchJson("/api/v1/receipts/{$id}", ['total' => 'banyak'])->assertStatus(422)->assertJsonValidationErrors(['total', 'receipt_date']);
});

it('needs the write ability to scan and hides other families [BR-I06]', function () {
    $id = $this->post('/api/v1/receipts', ['file' => apiReceiptImage()], ['Accept' => 'application/json'])->json('data.id');

    Sanctum::actingAs($this->owner, ['read']);
    $this->getJson("/api/v1/receipts/{$id}")->assertOk();
    $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])->assertForbidden();

    $stranger = User::factory()->create();
    createFamily($stranger, 'Keluarga Lain');
    Sanctum::actingAs($stranger, ['read', 'write']);
    $this->getJson("/api/v1/receipts/{$id}")->assertNotFound();
});
