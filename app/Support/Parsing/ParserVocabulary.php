<?php

declare(strict_types=1);

namespace App\Support\Parsing;

use App\Enums\CategoryType;

/**
 * The family's wallets and categories the text parser may match against.
 * Plain data, so the parser stays pure and testable without a database.
 */
final readonly class ParserVocabulary
{
    /**
     * @param  array<int, string>  $wallets  id => name, in display order (the first is the default)
     * @param  list<array{id: int, name: string, type: CategoryType, keywords: list<string>}>  $categories
     */
    public function __construct(
        public array $wallets = [],
        public array $categories = [],
    ) {}
}
