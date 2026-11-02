<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Receipt\ReceiptService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * BR-H10: expire unconfirmed drafts and delete files of rejected, expired and
 * failed receipts after 7 days. Scheduled hourly.
 */
#[Signature('receipts:expire')]
#[Description('Expire receipt drafts older than 24 hours and delete stale receipt files')]
class ExpireReceiptsCommand extends Command
{
    public function handle(ReceiptService $receipts): int
    {
        $result = $receipts->cleanupAllFamilies();

        $this->info("{$result['expired']} draft kedaluwarsa, {$result['files_deleted']} file dihapus.");

        return self::SUCCESS;
    }
}
