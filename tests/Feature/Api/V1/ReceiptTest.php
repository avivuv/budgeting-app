<?php

use App\DTOs\Receipt\ParsedReceiptData;
use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Receipt\Parsers\FakeReceiptParser;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
| Scenarios: docs/test-cases/receipts.md · Contract: docs/api-contracts/receipts.md
|
| A new family with default wallet & categories; the AI is a fake parser that
| reads an Alfamart receipt: rice 75.000 (groceries), 3 × tea 5.000 (food),
| discount 3.000, total 87.000. The queue runs synchronously in tests.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Storage::fake(config('filesystems.uploads'));

    $this->ayah = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->ayah);
    $this->ibu = addMember($this->family, FamilyRole::Member, User::factory()->create(['name' => 'Ibu']));
    $this->parser = new FakeReceiptParser;
    app()->instance(ReceiptParserInterface::class, $this->parser);

    $this->wallet = Wallet::query()->firstOrFail();
    $this->groceries = Category::query()->where('name', 'Belanja Harian')->firstOrFail();
    $this->food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();

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
    $this->alfamart = ParsedReceiptData::fromOutput($output, 'fake', $output);
});

function receiptAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

function receiptPhoto(): UploadedFile
{
    $image = imagecreatetruecolor(400, 700);
    imagesetpixel($image, random_int(0, 399), random_int(0, 699), random_int(1, 0xFFFFFF));
    imagestring($image, 5, 10, 10, (string) random_int(1, PHP_INT_MAX), 0xFFFFFF);
    $path = tempnam(sys_get_temp_dir(), 'rcp').'.jpg';
    imagejpeg($image, $path);

    return new UploadedFile($path, 'struk.jpg', 'image/jpeg', null, true);
}

function uploadReceipt(?UploadedFile $file = null): TestResponse
{
    return test()->post('/api/v1/receipts', $file !== null ? ['file' => $file] : [], ['Accept' => 'application/json']);
}

/** Upload the Alfamart receipt and return its id. */
function alfamartDraft(): int
{
    test()->parser->respondWith(test()->alfamart);

    return uploadReceipt(receiptPhoto())->assertStatus(202)->json('data.id');
}

function receiptTransactions(): int
{
    return Transaction::query()->where('source', 'receipt')->count();
}

describe('positive', function () {
    it('[RCP-01] uploads a photo and reads it into a draft, without booking anything [BR-H05] [BR-H08]', function () {
        // GIVEN Ayah and the AI reading an Alfamart receipt
        receiptAs($this->ayah);
        $this->parser->respondWith($this->alfamart);

        // WHEN he uploads the photo
        $upload = uploadReceipt(receiptPhoto());

        // THEN the scan counts, the draft carries the reading and no transaction exists
        $upload->assertStatus(202)->assertJsonPath('meta.quota.used', 1);
        $id = $upload->json('data.id');

        $this->getJson("/api/v1/receipts/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'parsed')
            ->assertJsonPath('data.status_label', 'Menunggu konfirmasi')
            ->assertJsonPath('data.document_type', 'receipt')
            ->assertJsonPath('data.document_type_label', 'Struk')
            ->assertJsonPath('data.merchant', 'Alfamart')
            ->assertJsonPath('data.receipt_date', '2026-10-31')
            ->assertJsonPath('data.total', 87_000)
            ->assertJsonPath('data.total_formatted', 'Rp 87.000')
            ->assertJsonPath('data.discount', 3_000)
            ->assertJsonPath('data.category_id', $this->groceries->id)
            ->assertJsonPath('data.wallet_id', $this->wallet->id)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.1.name', 'Teh Botol')
            ->assertJsonPath('data.items.1.category_id', $this->food->id)
            ->assertJsonPath('data.items_total', 90_000)
            ->assertJsonPath('data.has_file', true)
            ->assertJsonPath('data.image_url', "/api/v1/receipts/{$id}/image")
            ->assertJsonPath('data.transactions', []);

        expect(Transaction::query()->count())->toBe(0);
    });

    it('[RCP-02] corrects the draft; the corrections are what is read back [BR-H09]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

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
            ->assertJsonPath('data.merchant', 'Alfamart Kemang')
            ->assertJsonPath('data.total', 99_000);

        $this->getJson("/api/v1/receipts/{$id}")
            ->assertJsonPath('data.status', 'parsed')
            ->assertJsonPath('data.receipt_date', '2026-10-30')
            ->assertJsonPath('data.total', 99_000)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.2.total', 12_000)
            ->assertJsonPath('data.items_total', 102_000);
        expect(Transaction::query()->count())->toBe(0)
            ->and(Receipt::query()->find($id)->raw_response)->not->toBeNull();
    });

    it('[RCP-03] saves a corrected receipt split per category; the parts add up to the total [BR-H02] [INV-09]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();
        $balanceBefore = collect($this->getJson('/api/v1/wallets')->json('data'))->firstWhere('id', $this->wallet->id)['balance'];
        $this->patchJson("/api/v1/receipts/{$id}", [
            'merchant' => 'Alfamart Kemang', 'receipt_date' => '2026-10-30', 'total' => '99rb',
            'category_id' => $this->groceries->id, 'wallet_id' => $this->wallet->id,
            'items' => [
                ['name' => 'Beras 5kg', 'total' => 75_000, 'category_id' => $this->groceries->id],
                ['name' => 'Teh Botol', 'total' => 15_000, 'category_id' => $this->food->id],
                ['name' => 'Roti', 'total' => 12_000, 'category_id' => $this->food->id],
            ],
        ])->assertOk();

        $confirmed = $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'split'])
            ->assertCreated()
            ->assertJsonPath('message', 'Struk tersimpan sebagai 2 transaksi.')
            ->assertJsonPath('data.status', 'confirmed')
            ->json('data.transactions');

        // 102.000 of items → 99.000 paid: groceries 75.000 × 99/102 = 72.794,1; food 26.205,8; leftover 1 to the biggest.
        $amounts = collect($confirmed)->pluck('amount', 'category.id')->all();
        expect($amounts)->toEqual([$this->groceries->id => 72_795, $this->food->id => 26_205])
            ->and(array_sum($amounts))->toBe(99_000);

        $listed = $this->getJson('/api/v1/transactions?source=receipt')->assertOk();
        expect($listed->json('meta.totals.expense'))->toBe(99_000)
            ->and(collect($listed->json('data'))->pluck('date')->unique()->all())->toBe(['2026-10-30'])
            ->and(collect($listed->json('data'))->pluck('note')->unique()->all())->toBe(['Alfamart Kemang'])
            ->and(collect($listed->json('data'))->pluck('source')->unique()->all())->toBe(['receipt']);

        $balanceAfter = collect($this->getJson('/api/v1/wallets')->json('data'))->firstWhere('id', $this->wallet->id)['balance'];
        expect($balanceBefore - $balanceAfter)->toBe(99_000);
    });

    it('[RCP-04] saves the paid total as one transaction [BR-H01]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])
            ->assertCreated()
            ->assertJsonPath('message', 'Struk tersimpan sebagai transaksi.')
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.amount', 87_000)
            ->assertJsonPath('data.transactions.0.category.name', 'Belanja Harian');
    });

    it('[RCP-05] books nothing more when Simpan is pressed twice [BR-H07] [BR-B13]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $first = $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'split'])->assertCreated();
        $second = $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])->assertCreated();

        expect($second->json('data.transactions'))->toEqual($first->json('data.transactions'))
            ->and(receiptTransactions())->toBe(2);
    });

    it('[RCP-06] cancels a draft without creating a transaction [BR-H08]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $this->deleteJson("/api/v1/receipts/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Struk dibatalkan, tidak ada transaksi yang dibuat.')
            ->assertJsonPath('data.status', 'rejected');

        $this->getJson("/api/v1/receipts/{$id}")->assertJsonPath('data.status', 'rejected');
        expect(Transaction::query()->count())->toBe(0);
    });

    it('[RCP-07] lists the latest receipts with the quota and serves the image to members', function () {
        receiptAs($this->ayah);
        $first = alfamartDraft();
        $second = alfamartDraft();

        $this->getJson('/api/v1/receipts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.1.id', $first)
            ->assertJsonPath('meta.quota.used', 2)
            ->assertJsonPath('meta.quota.remaining', $this->family->receipt_quota_monthly - 2)
            ->assertJsonPath('meta.max_file_mb', 10);

        $image = $this->get("/api/v1/receipts/{$first}/image")->assertOk();
        expect($image->headers->get('content-type'))->toBe('image/jpeg');
    });

    it('[RCP-08] lets a member scan and save, and a viewer read [BR-B09]', function () {
        receiptAs($this->ibu);
        $id = alfamartDraft();
        $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])
            ->assertCreated()
            ->assertJsonPath('data.transactions.0.created_by.name', 'Ibu');

        receiptAs(addMember($this->family, FamilyRole::Viewer), ['read']);
        $this->getJson("/api/v1/receipts/{$id}")->assertOk();
        $this->get("/api/v1/receipts/{$id}/image")->assertOk();
        $this->getJson('/api/v1/receipts')->assertOk()->assertJsonCount(1, 'data');
    });
});

describe('negative', function () {
    it('[RCP-21] refuses a missing or unsupported file without using the quota [BR-H11] [BR-H05]', function () {
        receiptAs($this->ayah);

        uploadReceipt()->assertUnprocessable()->assertJsonValidationErrors('file');
        uploadReceipt(UploadedFile::fake()->createWithContent('catatan.txt', 'halo'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Format file tidak didukung. Kirim foto (JPG, PNG, WEBP, HEIC) atau PDF.')
            ->assertJsonPath('rule', 'BR-H11');

        $this->getJson('/api/v1/receipts')->assertJsonPath('meta.quota.used', 0)->assertJsonPath('data', []);
    });

    it('[RCP-22] refuses the same file twice [BR-H04]', function () {
        receiptAs($this->ayah);
        $photo = receiptPhoto();
        $path = tempnam(sys_get_temp_dir(), 'dup').'.jpg';
        copy($photo->getRealPath(), $path);
        $copy = new UploadedFile($path, 'struk.jpg', 'image/jpeg', null, true);

        uploadReceipt($photo)->assertStatus(202);
        uploadReceipt($copy)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'File struk yang sama sudah pernah diunggah.')
            ->assertJsonPath('rule', 'BR-H04');

        expect(Receipt::query()->count())->toBe(1);
    });

    it('[RCP-23] answers 429 when the monthly quota is used up [BR-H05]', function () {
        $this->family->forceFill(['receipt_quota_monthly' => 1])->save();
        receiptAs($this->ayah);

        uploadReceipt(receiptPhoto())->assertStatus(202);
        uploadReceipt(receiptPhoto())
            ->assertStatus(429)
            ->assertJsonPath('errors.file.0', 'Kuota scan struk bulan ini (1) sudah habis. Catat transaksi secara manual.');

        expect(Receipt::query()->count())->toBe(1);
    });

    it('[RCP-24] rejects an unreadable total and a missing date; the draft stays [BR-H09]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $this->patchJson("/api/v1/receipts/{$id}", ['total' => 'banyak'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total', 'receipt_date']);

        $this->getJson("/api/v1/receipts/{$id}")->assertJsonPath('data.total', 87_000)->assertJsonPath('data.merchant', 'Alfamart');
    });

    it('[RCP-25] does not change or save a receipt that is no longer waiting [BR-H07] [BR-H09]', function () {
        receiptAs($this->ayah);
        $saved = alfamartDraft();
        $this->postJson("/api/v1/receipts/{$saved}/confirm", ['mode' => 'total'])->assertCreated();
        $cancelled = alfamartDraft();
        $this->deleteJson("/api/v1/receipts/{$cancelled}")->assertOk();

        $this->patchJson("/api/v1/receipts/{$saved}", ['receipt_date' => '2026-10-31', 'total' => 1_000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Hanya hasil baca yang menunggu konfirmasi yang bisa diubah.');
        $this->postJson("/api/v1/receipts/{$cancelled}/confirm", ['mode' => 'total'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Struk ini tidak sedang menunggu konfirmasi.')
            ->assertJsonPath('rule', 'BR-H07');
        $this->deleteJson("/api/v1/receipts/{$saved}")->assertUnprocessable()->assertJsonValidationErrors('status');

        expect(receiptTransactions())->toBe(1);
    });

    it('[RCP-26] asks for a wallet before saving [BR-H08]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();
        $this->patchJson("/api/v1/receipts/{$id}", ['receipt_date' => '2026-10-31', 'total' => 87_000, 'category_id' => $this->groceries->id, 'wallet_id' => null])->assertOk();

        $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.wallet_id.0', 'Pilih dompet sebelum menyimpan.');

        expect(Transaction::query()->count())->toBe(0);
    });

    it('[RCP-27] treats a category or wallet of another family as not found [BR-I06]', function () {
        $neighbour = User::factory()->create();
        $other = createFamily($neighbour, 'Tetangga');
        $foreignWallet = Wallet::query()->withoutGlobalScopes()->where('family_id', $other->id)->firstOrFail();
        $foreignCategory = Category::query()->withoutGlobalScopes()->where('family_id', $other->id)->where('type', 'expense')->firstOrFail();
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $this->patchJson("/api/v1/receipts/{$id}", ['receipt_date' => '2026-10-31', 'total' => 1_000, 'wallet_id' => $foreignWallet->id])->assertNotFound();
        $this->patchJson("/api/v1/receipts/{$id}", ['receipt_date' => '2026-10-31', 'total' => 1_000, 'category_id' => $foreignCategory->id])->assertNotFound();

        $this->getJson("/api/v1/receipts/{$id}")->assertJsonPath('data.total', 87_000)->assertJsonPath('data.wallet_id', $this->wallet->id);
    });

    it('[RCP-28] does not let a viewer or a read-only token scan, correct, save or cancel [BR-B09]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();
        $viewer = addMember($this->family, FamilyRole::Viewer);

        foreach ([[$viewer, ['read', 'write']], [$this->ayah, ['read']]] as [$user, $abilities]) {
            receiptAs($user, $abilities);
            uploadReceipt(receiptPhoto())->assertForbidden();
            $this->patchJson("/api/v1/receipts/{$id}", ['receipt_date' => '2026-10-31', 'total' => 1_000])->assertForbidden();
            $this->postJson("/api/v1/receipts/{$id}/confirm", ['mode' => 'total'])->assertForbidden();
            $this->deleteJson("/api/v1/receipts/{$id}")->assertForbidden();
        }

        expect(Receipt::query()->count())->toBe(1)
            ->and(Receipt::query()->sole()->status->value)->toBe('parsed')
            ->and(Transaction::query()->count())->toBe(0);
    });

    it('[RCP-29] hides drafts and images from another family [BR-I06]', function () {
        receiptAs($this->ayah);
        $id = alfamartDraft();

        $neighbour = User::factory()->create();
        createFamily($neighbour, 'Tetangga');
        receiptAs($neighbour);

        $this->getJson("/api/v1/receipts/{$id}")->assertNotFound();
        $this->get("/api/v1/receipts/{$id}/image", ['Accept' => 'application/json'])->assertNotFound();
        $this->getJson('/api/v1/receipts')->assertJsonPath('data', []);
    });
});
