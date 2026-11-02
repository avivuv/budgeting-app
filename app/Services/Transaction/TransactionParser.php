<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\DTOs\Transaction\ParsedTransactionData;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Wallet;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use App\Support\Parsing\ParsedLine;
use App\Support\Parsing\ParserVocabulary;
use App\Support\Parsing\TransactionTextParser;
use Carbon\CarbonImmutable;

/**
 * Reads free text ("bensin 50rb") against the active family's wallets and
 * categories. Used by web quick entry, the REST API and later the Telegram
 * bot, so every channel understands the same input (BR-B12). Never saves.
 */
class TransactionParser
{
    public function __construct(
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return list<ParsedTransactionData>
     */
    public function parse(string $text): array
    {
        $wallets = [];
        foreach ($this->wallets->listForFamily() as $wallet) {
            /** @var Wallet $wallet */
            $wallets[$wallet->id] = $wallet->name;
        }

        $categories = [];
        $names = [];
        foreach ($this->categories->tree() as $parent) {
            foreach ([$parent, ...$parent->children] as $category) {
                /** @var Category $category */
                $names[$category->id] = $category->name;
                $categories[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'keywords' => $category->keywords ?? [],
                ];
            }
        }

        $today = CarbonImmutable::now($this->tenant->get()->timezone)->startOfDay();
        $lines = TransactionTextParser::parse($text, $today, new ParserVocabulary($wallets, $categories));
        $defaultWallet = array_key_first($wallets);

        return array_map(function (ParsedLine $line) use ($wallets, $names, $defaultWallet): ParsedTransactionData {
            if ($line->walletId === null && $defaultWallet !== null) {
                $line = $line->withWallet($defaultWallet);
            }

            // BR-E05 step 3: the family's history for the same note.
            if ($line->categoryId === null && $line->type !== TransactionType::Transfer && $line->note !== null) {
                $fromHistory = $this->transactions->mostUsedCategoryForNote($line->note, $line->type);

                if ($fromHistory !== null && isset($names[$fromHistory])) {
                    $line = $line->withCategory($fromHistory, ParsedLine::CATEGORY_HISTORY);
                }
            }

            return ParsedTransactionData::fromLine($line, $wallets, $names);
        }, $lines);
    }
}
