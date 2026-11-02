<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Kinds of proof of payment that are accepted (BR-H11).
 */
#[TypeScript]
enum ReceiptDocumentType: string
{
    case Receipt = 'receipt';
    case TransferProof = 'transfer_proof';
    case Ewallet = 'ewallet';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Struk',
            self::TransferProof => 'Bukti transfer',
            self::Ewallet => 'E-wallet',
            self::Invoice => 'Invoice',
        };
    }
}
