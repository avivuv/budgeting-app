<?php

declare(strict_types=1);

namespace App\DTOs\Receipt;

use Spatie\LaravelData\Data;

/**
 * The family's own names, so the AI picks a category and wallet from them
 * (docs/06 §4).
 */
class ReceiptParseContextData extends Data
{
    /**
     * @param  list<string>  $categories  expense category names
     * @param  list<string>  $wallets  wallet names
     */
    public function __construct(
        public array $categories,
        public array $wallets,
        public string $today,
    ) {}
}
