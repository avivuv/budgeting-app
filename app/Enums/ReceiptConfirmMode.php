<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * "total": one transaction for the paid total (BR-H01). "split": one
 * transaction per item category (BR-H02).
 */
#[TypeScript]
enum ReceiptConfirmMode: string
{
    case Total = 'total';
    case Split = 'split';
}
