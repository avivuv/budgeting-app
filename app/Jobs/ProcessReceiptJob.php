<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\Receipt\ReceiptParserException;
use App\Models\Receipt;
use App\Services\Receipt\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Reads an uploaded receipt in the background (docs/06 §3). Carries ids only;
 * the family becomes the tenant before anything is loaded.
 */
class ProcessReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 90;

    public function __construct(
        public readonly int $familyId,
        public readonly int $receiptId,
    ) {
        $this->onQueue(config('receipt.queue'));
    }

    /**
     * Free AI tiers allow a few requests per minute (docs/06 §5.1).
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('receipts')];
    }

    public function handle(ReceiptService $receipts): void
    {
        try {
            $receipts->forQueued($this->familyId, $this->receiptId, fn (Receipt $receipt): Receipt => $receipts->process($receipt));
        } catch (ReceiptParserException $e) {
            // The provider asked us to slow down: try again later without using a try.
            if ($e->rateLimited && $this->job !== null) {
                $this->release(60);

                return;
            }

            throw $e;
        }
    }

    /**
     * All tries used: mark it failed and give the scan back (BR-H05).
     */
    public function failed(?Throwable $exception): void
    {
        app(ReceiptService::class)->forQueued(
            $this->familyId,
            $this->receiptId,
            fn (Receipt $receipt): Receipt => app(ReceiptService::class)->markFailed($receipt, $exception?->getMessage() ?? 'unknown error'),
        );
    }
}
