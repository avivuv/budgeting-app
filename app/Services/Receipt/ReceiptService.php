<?php

declare(strict_types=1);

namespace App\Services\Receipt;

use App\DTOs\Receipt\ParsedReceiptData;
use App\DTOs\Receipt\ParsedReceiptItemData;
use App\DTOs\Receipt\ReceiptDraftData;
use App\DTOs\Receipt\ReceiptItemInputData;
use App\DTOs\Receipt\ReceiptParseContextData;
use App\DTOs\Transaction\TransactionData;
use App\Enums\CategoryType;
use App\Enums\ReceiptConfirmMode;
use App\Enums\ReceiptStatus;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\Receipt\ReceiptQuotaExceededException;
use App\Exceptions\Receipt\ReceiptRuleException;
use App\Jobs\ProcessReceiptJob;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\ReceiptRepositoryInterface;
use App\Repositories\Contracts\UsageLogRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use App\Services\Report\PeriodService;
use App\Services\Storage\FileStorageService;
use App\Services\Tenancy\TenantContext;
use App\Services\Transaction\TransactionService;
use App\Support\Money\AmountParser;
use App\Support\Receipt\CategoryMatcher;
use App\Support\Receipt\ReceiptChecker;
use App\Support\Receipt\ReceiptSplitter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Receipt scanning for every channel — web, REST API and later Telegram
 * (docs/06). A reading is only a draft until the user confirms it (BR-H08).
 */
class ReceiptService
{
    public function __construct(
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly UsageLogRepositoryInterface $usage,
        private readonly CategoryRepositoryInterface $categories,
        private readonly WalletRepositoryInterface $wallets,
        private readonly FamilyRepositoryInterface $families,
        private readonly TransactionService $transactions,
        private readonly FileStorageService $files,
        private readonly ReceiptParserInterface $parser,
        private readonly PeriodService $periods,
        private readonly TenantContext $tenant,
    ) {}

    public function find(int $id): Receipt
    {
        return $this->receipts->findOrFail($id);
    }

    /**
     * @return Collection<int, Receipt>
     */
    public function recent(int $limit = 10): Collection
    {
        return $this->receipts->recent($limit);
    }

    /**
     * Scans used this month and the family's monthly quota (docs/06 §6).
     *
     * @return array{used: int, limit: int, remaining: int}
     */
    public function quota(): array
    {
        $used = $this->usage->countFor(UsageLog::RECEIPT_SCAN, $this->usagePeriod(CarbonImmutable::now()));
        $limit = $this->tenant->get()->receipt_quota_monthly;

        return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used)];
    }

    /**
     * Accept an upload: check it (BR-H11, BR-H04, BR-H05), shrink images, store
     * the file privately and queue the reading.
     */
    public function store(string $localPath, ?string $originalName, User $actor, ?int $telegramChatId = null, ?int $telegramMessageId = null): Receipt
    {
        $mime = $this->assertAcceptable($localPath, $originalName);

        // BR-H04: hash of the file as sent, before resizing.
        $hash = (string) hash_file('sha256', $localPath);
        if ($this->receipts->hasActiveFile($hash)) {
            throw ReceiptRuleException::duplicateFile();
        }

        $quota = $this->quota();
        if ($quota['remaining'] <= 0) {
            throw new ReceiptQuotaExceededException($quota['limit']);
        }

        [$storedFrom, $mime, $extension] = $this->prepareFile($localPath, $mime);

        $path = $this->files->storeReceipt($this->tenant->id(), $storedFrom, $extension);
        if ($storedFrom !== $localPath) {
            @unlink($storedFrom);
        }

        try {
            $receipt = DB::transaction(function () use ($path, $hash, $mime, $actor, $telegramChatId, $telegramMessageId): Receipt {
                // BR-H05: the scan counts as soon as the upload is accepted.
                $this->usage->incrementFor(UsageLog::RECEIPT_SCAN, $this->usagePeriod(CarbonImmutable::now()));

                return $this->receipts->create([
                    'user_id' => $actor->id,
                    'file_path' => $path,
                    'file_hash' => $hash,
                    'mime_type' => $mime,
                    'status' => ReceiptStatus::Pending,
                    'telegram_chat_id' => $telegramChatId,
                    'telegram_message_id' => $telegramMessageId,
                ]);
            });
        } catch (Throwable $e) {
            $this->files->delete($path);

            throw $e;
        }

        ProcessReceiptJob::dispatch($this->tenant->id(), $receipt->id);

        return $receipt;
    }

    /**
     * Queued step: read the file and save the reading as a draft (status
     * parsed). Never creates a transaction (BR-H08).
     */
    public function process(Receipt $receipt): Receipt
    {
        if (! $receipt->status->isReading() || $receipt->file_path === null) {
            return $receipt;
        }

        $receipt = $this->receipts->update($receipt, ['status' => ReceiptStatus::Processing]);
        $context = $this->parseContext();

        $parsed = $this->files->withLocalFile(
            $receipt->file_path,
            fn (string $path): ParsedReceiptData => $this->parser->parse($path, $receipt->mime_type, $context),
        );

        if (! $parsed->isReceipt) {
            // Not a receipt: the scan is not given back (BR-H05).
            return $this->receipts->update($receipt, [
                'status' => ReceiptStatus::Failed,
                'error' => 'Ini sepertinya bukan struk atau bukti transaksi.',
                'raw_response' => $parsed->raw,
                'model' => $parsed->model,
            ]);
        }

        return $this->applyReading($receipt, $parsed);
    }

    /**
     * The reading failed for good (provider/system error): mark it and give
     * the scan back (BR-H05).
     */
    public function markFailed(Receipt $receipt, string $error): Receipt
    {
        if (! $receipt->status->isReading()) {
            return $receipt;
        }

        $this->usage->decrementFor(UsageLog::RECEIPT_SCAN, $this->usagePeriod(CarbonImmutable::parse($receipt->created_at)));

        return $this->receipts->update($receipt, [
            'status' => ReceiptStatus::Failed,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }

    /**
     * Run a queued step for a receipt of the given family (the job carries ids only).
     *
     * @template T
     *
     * @param  callable(Receipt): T  $callback
     * @return T|null null when the family or receipt is gone
     */
    public function forQueued(int $familyId, int $receiptId, callable $callback): mixed
    {
        $family = $this->families->find($familyId);

        if ($family === null) {
            return null;
        }

        return $this->tenant->run($family, function () use ($receiptId, $callback): mixed {
            $receipt = $this->receipts->find($receiptId);

            return $receipt !== null ? $callback($receipt) : null;
        });
    }

    /**
     * BR-H09: the user corrects the draft; the AI output stays in raw_response.
     */
    public function update(Receipt $receipt, ReceiptDraftData $data): Receipt
    {
        if ($receipt->status !== ReceiptStatus::Parsed) {
            throw ReceiptRuleException::notEditable();
        }

        if ($data->total <= 0 || $data->total > AmountParser::MAX) {
            throw ReceiptRuleException::totalRequired();
        }

        // Tenant-scoped lookups: ids of another family are "not found" (BR-I06).
        $this->assertCategory($data->categoryId);
        if ($data->walletId !== null) {
            $this->wallets->findOrFail($data->walletId);
        }

        return DB::transaction(function () use ($receipt, $data): Receipt {
            if ($data->items !== null) {
                $items = [];
                foreach ($data->items as $item) {
                    /** @var ReceiptItemInputData $item */
                    $this->assertCategory($item->categoryId);
                    $items[] = [
                        'name' => mb_substr(trim($item->name), 0, 120),
                        'qty' => $item->qty > 0 ? round($item->qty, 2) : 1.0,
                        'unit_price' => $item->unitPrice,
                        'total' => $item->total,
                        'category_id' => $item->categoryId,
                    ];
                }
                $this->receipts->replaceItems($receipt, $items);
            }

            $merchant = $data->merchant !== null ? trim($data->merchant) : null;

            $receipt = $this->receipts->update($receipt, [
                'merchant' => $merchant === '' ? null : $merchant,
                'receipt_date' => $data->receiptDate,
                'total' => $data->total,
                'suggested_category_id' => $data->categoryId,
                'suggested_wallet_id' => $data->walletId,
            ]);

            // The user chose the date now, so it is no longer "replaced".
            return $this->refreshFlags($receipt, dateReplaced: false);
        });
    }

    /**
     * Turn the draft into transactions — only now, after the user pressed
     * Simpan (BR-H08). Pressing twice keeps one result (BR-H07, BR-B13).
     */
    public function confirm(Receipt $receipt, ReceiptConfirmMode $mode, User $actor): Receipt
    {
        return Cache::lock("receipt:{$receipt->id}:confirm", 30)->block(10, fn (): Receipt => DB::transaction(function () use ($receipt, $mode, $actor): Receipt {
            $locked = $this->receipts->findForUpdate($receipt->id);

            if ($locked->status === ReceiptStatus::Confirmed) {
                return $locked;
            }

            if ($locked->status !== ReceiptStatus::Parsed) {
                throw ReceiptRuleException::notConfirmable();
            }

            if ($locked->total === null || $locked->total <= 0) {
                throw ReceiptRuleException::totalRequired();
            }

            if ($locked->suggested_wallet_id === null) {
                throw ReceiptRuleException::walletRequired();
            }

            if ($locked->suggested_category_id === null) {
                throw ReceiptRuleException::categoryRequired();
            }

            foreach ($this->amounts($locked, $mode) as $categoryId => $amount) {
                $this->transactions->create(new TransactionData(
                    type: TransactionType::Expense,
                    amount: $amount,
                    date: $locked->receipt_date ?? $this->periods->today(),
                    walletId: $locked->suggested_wallet_id,
                    categoryId: $categoryId,
                    note: $locked->merchant,
                    source: TransactionSource::Receipt,
                    merchant: $locked->merchant,
                    receiptId: $locked->id,
                ), $actor);
            }

            return $this->receipts->update($locked, [
                'status' => ReceiptStatus::Confirmed,
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);
        }));
    }

    /**
     * "Batal": no transaction is created; the file is removed later (BR-H10).
     */
    public function reject(Receipt $receipt): Receipt
    {
        if (! in_array($receipt->status, [ReceiptStatus::Parsed, ReceiptStatus::Failed], true)) {
            throw ReceiptRuleException::notEditable();
        }

        return $this->receipts->update($receipt, ['status' => ReceiptStatus::Rejected, 'expires_at' => null]);
    }

    /**
     * BR-H10: drafts older than 24 hours expire; files of rejected, expired
     * and failed receipts are deleted after 7 days. For the active family.
     *
     * @return array{expired: int, files_deleted: int}
     */
    public function cleanup(): array
    {
        $expired = 0;
        foreach ($this->receipts->expiredDrafts(now()) as $receipt) {
            $this->receipts->update($receipt, ['status' => ReceiptStatus::Expired]);
            $expired++;
        }

        $deleted = 0;
        foreach ($this->receipts->withStaleFiles(now()->subDays((int) config('receipt.delete_files_after_days'))) as $receipt) {
            $this->files->delete($receipt->file_path);
            $this->receipts->update($receipt, ['file_path' => null]);
            $deleted++;
        }

        return ['expired' => $expired, 'files_deleted' => $deleted];
    }

    /**
     * Run cleanup() for every family.
     *
     * @return array{expired: int, files_deleted: int}
     */
    public function cleanupAllFamilies(): array
    {
        $total = ['expired' => 0, 'files_deleted' => 0];

        foreach ($this->families->all() as $family) {
            $result = $this->tenant->run($family, fn (): array => $this->cleanup());
            $total['expired'] += $result['expired'];
            $total['files_deleted'] += $result['files_deleted'];
        }

        return $total;
    }

    /**
     * Category => amount for the transactions to create. "split" shares the
     * total over the item categories (BR-H02); items without a category use
     * the receipt's category.
     *
     * @return array<int, int>
     */
    private function amounts(Receipt $receipt, ReceiptConfirmMode $mode): array
    {
        /** @var int $category */
        $category = $receipt->suggested_category_id;
        /** @var int $total */
        $total = $receipt->total;

        if ($mode === ReceiptConfirmMode::Total) {
            return [$category => $total];
        }

        $items = $this->receipts->items($receipt);
        if ($items->isEmpty()) {
            throw ReceiptRuleException::nothingToSplit();
        }

        $subtotals = [];
        foreach ($items as $item) {
            $key = $item->category_id ?? $category;
            $subtotals[$key] = ($subtotals[$key] ?? 0) + $item->total;
        }

        return ReceiptSplitter::split($subtotals, $total, $category);
    }

    private function applyReading(Receipt $receipt, ParsedReceiptData $parsed): Receipt
    {
        $matcher = new CategoryMatcher($this->expenseCategories());

        $items = array_map(fn (ParsedReceiptItemData $item): array => [
            'name' => $item->name,
            'qty' => $item->qty,
            'unit_price' => $item->unitPrice,
            'total' => $item->total,
            'category_id' => $matcher->match($item->category) ?? $matcher->match($item->name),
        ], $parsed->items);

        $category = $matcher->match($parsed->suggestedCategory);
        if ($category === null) {
            $category = $this->mostCommonCategory($items);
        }

        [$date, $dateReplaced] = ReceiptChecker::date($parsed->date, $this->periods->today());

        return DB::transaction(function () use ($receipt, $parsed, $items, $category, $date, $dateReplaced): Receipt {
            $this->receipts->replaceItems($receipt, $items);

            $receipt = $this->receipts->update($receipt, [
                'status' => ReceiptStatus::Parsed,
                'document_type' => $parsed->documentType,
                'merchant' => $parsed->merchant,
                'receipt_date' => $date->toDateString(),
                'total' => $parsed->total,
                'discount' => $parsed->discount,
                'tax' => $parsed->tax,
                'payment_method' => $parsed->paymentMethod,
                'suggested_category_id' => $category,
                'suggested_wallet_id' => $this->matchWallet($parsed->suggestedWallet),
                'confidence' => $parsed->confidence,
                'raw_response' => $parsed->raw,
                'model' => $parsed->model,
                'error' => null,
                'expires_at' => now()->addHours((int) config('receipt.draft_hours')),
            ]);

            return $this->refreshFlags($receipt, $dateReplaced, lowerConfidence: true);
        });
    }

    /**
     * BR-H03 total mismatch, BR-H04 possible duplicate, BR-H06 replaced date.
     */
    private function refreshFlags(Receipt $receipt, bool $dateReplaced, bool $lowerConfidence = false): Receipt
    {
        $itemTotals = array_values($this->receipts->items($receipt)->map(fn (ReceiptItem $item): int => $item->total)->all());

        $mismatch = ReceiptChecker::totalMismatch(
            $itemTotals,
            $receipt->discount,
            $receipt->tax,
            (int) $receipt->total,
            (float) config('receipt.mismatch_tolerance'),
        );

        $flags = [];
        if ($mismatch) {
            $flags[] = Receipt::FLAG_TOTAL_MISMATCH;
        }
        if ($dateReplaced) {
            $flags[] = Receipt::FLAG_DATE_REPLACED;
        }
        if ($this->receipts->hasSimilar($receipt)) {
            $flags[] = Receipt::FLAG_POSSIBLE_DUPLICATE;
        }

        $attributes = ['flags' => $flags];
        if ($mismatch && $lowerConfidence) {
            $attributes['confidence'] = ReceiptChecker::lowerConfidence($receipt->confidence);
        }

        return $this->receipts->update($receipt, $attributes);
    }

    /**
     * BR-H11: allowed format, size and PDF pages — checked before the quota is used.
     */
    private function assertAcceptable(string $path, ?string $originalName): string
    {
        $mime = (string) (mime_content_type($path) ?: '');
        $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));

        // HEIC is often not recognised by libmagic.
        if (in_array($mime, ['', 'application/octet-stream'], true) && in_array($extension, ['heic', 'heif'], true)) {
            $mime = 'image/'.$extension;
        }

        if (! in_array($mime, config('receipt.mime_types'), true)) {
            throw ReceiptRuleException::unsupportedType();
        }

        $maxMb = (int) config('receipt.max_file_mb');
        if (filesize($path) > $maxMb * 1024 * 1024) {
            throw ReceiptRuleException::tooLarge($maxMb);
        }

        $maxPages = (int) config('receipt.max_pdf_pages');
        if ($mime === 'application/pdf' && self::pdfPages($path) > $maxPages) {
            throw ReceiptRuleException::tooManyPages($maxPages);
        }

        return $mime;
    }

    /**
     * Shrink JPG/PNG/WEBP to the configured size as JPEG; HEIC and PDF are kept as sent.
     *
     * @return array{0: string, 1: string, 2: string} file to store, mime type, extension
     */
    private function prepareFile(string $path, string $mime): array
    {
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [$path, $mime, match ($mime) {
                'application/pdf' => 'pdf',
                'image/heif' => 'heif',
                default => 'heic',
            }];
        }

        try {
            $max = (int) config('receipt.max_image_px');
            $image = (new ImageManager(new Driver))->read($path);
            $image->scaleDown($max, $max);

            $target = tempnam(sys_get_temp_dir(), 'rcp');
            $image->toJpeg((int) config('receipt.jpeg_quality'))->save($target);

            return [$target, 'image/jpeg', 'jpg'];
        } catch (Throwable) {
            // An image GD cannot decode is not a usable receipt.
            throw ReceiptRuleException::unsupportedType();
        }
    }

    private static function pdfPages(string $path): int
    {
        $pages = preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', (string) file_get_contents($path));

        return $pages === false ? 0 : $pages;
    }

    private function parseContext(): ReceiptParseContextData
    {
        return new ReceiptParseContextData(
            categories: array_column($this->expenseCategories(), 'name'),
            wallets: array_values($this->wallets->listForFamily()->map(fn (Wallet $wallet): string => $wallet->name)->all()),
            today: $this->periods->today()->toDateString(),
        );
    }

    /**
     * Active expense categories, sub-categories included.
     *
     * @return list<array{id: int, name: string, keywords: list<string>}>
     */
    private function expenseCategories(): array
    {
        $list = [];
        foreach ($this->categories->tree(CategoryType::Expense) as $parent) {
            foreach ([$parent, ...$parent->children] as $category) {
                /** @var Category $category */
                $list[] = ['id' => $category->id, 'name' => $category->name, 'keywords' => $category->keywords ?? []];
            }
        }

        return $list;
    }

    /**
     * The wallet the AI recognised (e.g. "GoPay" screenshot), else the first one.
     */
    private function matchWallet(?string $name): ?int
    {
        $wallets = $this->wallets->listForFamily();

        foreach ($wallets as $wallet) {
            /** @var Wallet $wallet */
            if ($name !== null && mb_strtolower($wallet->name) === mb_strtolower($name)) {
                return $wallet->id;
            }
        }

        return $wallets->first()?->id;
    }

    /**
     * @param  list<array{category_id: int|null, total: int}>  $items
     */
    private function mostCommonCategory(array $items): ?int
    {
        $sums = [];
        foreach ($items as $item) {
            if ($item['category_id'] !== null) {
                $sums[$item['category_id']] = ($sums[$item['category_id']] ?? 0) + $item['total'];
            }
        }

        if ($sums === []) {
            return null;
        }

        arsort($sums);

        return (int) array_key_first($sums);
    }

    private function assertCategory(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if ($this->categories->findOrFail($categoryId)->type !== CategoryType::Expense) {
            throw ReceiptRuleException::categoryRequired();
        }
    }

    /**
     * Usage is counted per budget period, stored as its label month.
     */
    private function usagePeriod(CarbonImmutable $moment): string
    {
        $day = CarbonImmutable::parse($moment->setTimezone($this->tenant->get()->timezone)->toDateString());

        return $this->periods->labelMonth($this->periods->containing($day))->toDateString();
    }
}
