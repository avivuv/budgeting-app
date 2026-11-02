<?php

declare(strict_types=1);

namespace App\Support\Parsing;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Support\Money\AmountParser;
use Carbon\CarbonImmutable;

/**
 * Free text → transaction fields (docs/05-telegram-bot.md §3.1), shared by the
 * web quick entry, the REST API and later the Telegram bot.
 *
 *   "bensin 50rb"                    expense 50.000, category by keyword
 *   "+ gaji 8jt @bca"                income into BCA
 *   "tf 500rb bca ke gopay"          transfer (also "bca>gopay")
 *   "makan 35rb kemarin #kantor"     yesterday, tag "kantor"
 *   "!transportasi parkir 5rb"       explicit category
 *
 * One line = one transaction. Category guess follows BR-E05: explicit "!name",
 * then keywords (whole words, case-insensitive); history is up to the caller.
 */
final class TransactionTextParser
{
    public const int MAX_LINES = 20;

    private const array INCOME_WORDS = ['gaji', 'gajian', 'terima', 'dapat', 'dapet', 'masuk', 'pemasukan'];

    private const array TRANSFER_WORDS = ['transfer', 'tf', 'pindah'];

    private const array WALLET_PREFIXES = ['pakai', 'pake', 'via'];

    private const array AMOUNT_UNITS = ['rb', 'ribu', 'k', 'jt', 'juta', 'm', 'miliar', 'milyar'];

    /**
     * @return list<ParsedLine>
     */
    public static function parse(string $text, CarbonImmutable $today, ParserVocabulary $vocabulary): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        return array_map(
            fn (string $line): ParsedLine => self::parseLine($line, $today, $vocabulary),
            array_slice($lines, 0, self::MAX_LINES),
        );
    }

    public static function parseLine(string $line, CarbonImmutable $today, ParserVocabulary $vocabulary): ParsedLine
    {
        $raw = trim($line);
        $today = $today->startOfDay();
        $type = TransactionType::Expense;

        $body = $raw;
        if (str_starts_with($body, '+')) {
            $type = TransactionType::Income;
            $body = ltrim(substr($body, 1));
        }

        /** @var list<string> $tokens */
        $tokens = preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lower = array_map(fn (string $t): string => mb_strtolower($t), $tokens);
        $used = array_fill(0, count($tokens), false);

        if (isset($lower[0]) && in_array($lower[0], self::TRANSFER_WORDS, true)) {
            $type = TransactionType::Transfer;
            $used[0] = true;
        }

        $tags = [];
        $categoryHint = null;
        $walletHints = [];
        $toWalletHint = null;

        foreach ($tokens as $i => $token) {
            if ($used[$i]) {
                continue;
            }

            if (preg_match('/^#([\p{L}\p{N}_-]+)$/u', $token, $m)) {
                $tags[] = mb_strtolower($m[1]);
                $used[$i] = true;
            } elseif (preg_match('/^!(.+)$/u', $token, $m)) {
                $categoryHint = str_replace(['-', '_'], ' ', mb_strtolower($m[1]));
                $used[$i] = true;
            } elseif (preg_match('/^@(.+)$/u', $token, $m)) {
                $walletHints[] = mb_strtolower($m[1]);
                $used[$i] = true;
            } elseif ($type === TransactionType::Transfer && preg_match('/^([^>]+)>([^>]+)$/u', $token, $m)) {
                $walletHints[] = mb_strtolower($m[1]);
                $toWalletHint = mb_strtolower($m[2]);
                $used[$i] = true;
            } elseif (in_array($lower[$i], self::WALLET_PREFIXES, true) && isset($tokens[$i + 1])) {
                $walletHints[] = $lower[$i + 1];
                $used[$i] = $used[$i + 1] = true;
            } elseif ($type === TransactionType::Transfer && $lower[$i] === 'ke' && isset($tokens[$i + 1])) {
                $toWalletHint = $lower[$i + 1];
                $used[$i] = $used[$i + 1] = true;
            }
        }

        $date = self::extractDate($lower, $used, $today);
        $amount = self::extractAmount($lower, $used);

        if ($type === TransactionType::Expense && array_intersect($lower, self::INCOME_WORDS) !== []) {
            $type = TransactionType::Income;
        }

        // A wallet named in plain words ("bensin 50rb bca") also counts.
        foreach ($lower as $i => $word) {
            if (! $used[$i] && self::findWallet($word, $vocabulary, exact: true) !== null) {
                if ($type === TransactionType::Transfer && $walletHints !== [] && $toWalletHint === null) {
                    $toWalletHint = $word;
                } else {
                    $walletHints[] = $word;
                }
                $used[$i] = true;
            }
        }

        $walletId = $walletHints !== [] ? self::findWallet($walletHints[0], $vocabulary) : null;
        $toWalletId = $toWalletHint !== null ? self::findWallet($toWalletHint, $vocabulary) : null;

        $remaining = [];
        foreach ($tokens as $i => $token) {
            if (! $used[$i]) {
                $remaining[] = $token;
            }
        }
        $note = $remaining === [] ? null : implode(' ', $remaining);

        [$categoryId, $categorySource] = $type === TransactionType::Transfer
            ? [null, null]
            : self::guessCategory($type, $categoryHint, $note, $vocabulary);

        $missing = [];
        if ($amount === null) {
            $missing[] = 'amount';
        }
        if ($type !== TransactionType::Transfer && $categoryId === null) {
            $missing[] = 'category_id';
        }
        if ($type === TransactionType::Transfer && $toWalletId === null) {
            $missing[] = 'to_wallet_id';
        }

        return new ParsedLine(
            raw: $raw,
            type: $type,
            amount: $amount,
            date: $date,
            walletId: $walletId,
            toWalletId: $toWalletId,
            categoryId: $categoryId,
            categorySource: $categorySource,
            note: $note,
            tags: array_values(array_unique($tags)),
            missing: $missing,
        );
    }

    /**
     * "kemarin", "N hari lalu", "tgl 25", "25/10", "25/10/2026". Never in the
     * future (BR-B06): a day after today means the previous month/year.
     *
     * @param  list<string>  $words
     * @param  array<int, bool>  $used
     */
    private static function extractDate(array $words, array &$used, CarbonImmutable $today): CarbonImmutable
    {
        foreach ($words as $i => $word) {
            if ($used[$i]) {
                continue;
            }

            if (in_array($word, ['kemarin', 'kmrn', 'kmarin'], true)) {
                $used[$i] = true;

                return $today->subDay();
            }

            if ($word === 'hari' && ($words[$i + 1] ?? null) === 'ini') {
                $used[$i] = $used[$i + 1] = true;

                return $today;
            }

            if (preg_match('/^\d{1,3}$/', $word) && ($words[$i + 1] ?? null) === 'hari' && in_array($words[$i + 2] ?? null, ['lalu', 'yg', 'yang'], true)) {
                $used[$i] = $used[$i + 1] = $used[$i + 2] = true;
                if ($words[$i + 2] !== 'lalu' && ($words[$i + 3] ?? null) === 'lalu') {
                    $used[$i + 3] = true;
                }

                return $today->subDays((int) $word);
            }

            if (in_array($word, ['tgl', 'tanggal'], true) && preg_match('/^\d{1,2}$/', $words[$i + 1] ?? '')) {
                $day = (int) $words[$i + 1];
                $date = self::dayOfMonth($today, $day);

                if ($date !== null) {
                    $used[$i] = $used[$i + 1] = true;

                    return $date;
                }
            }

            if (preg_match('#^(\d{1,2})/(\d{1,2})(?:/(\d{2}|\d{4}))?$#', $word, $m)) {
                $year = isset($m[3]) ? (int) (strlen($m[3]) === 2 ? '20'.$m[3] : $m[3]) : $today->year;

                if (checkdate((int) $m[2], (int) $m[1], $year)) {
                    $used[$i] = true;
                    $date = CarbonImmutable::create($year, (int) $m[2], (int) $m[1], 0, 0, 0, $today->timezone);

                    return ! isset($m[3]) && $date->greaterThan($today) ? $date->subYear() : $date;
                }
            }
        }

        return $today;
    }

    private static function dayOfMonth(CarbonImmutable $today, int $day): ?CarbonImmutable
    {
        if ($day < 1 || $day > 31) {
            return null;
        }

        $month = $day > $today->day ? $today->startOfMonth()->subMonthNoOverflow() : $today->startOfMonth();

        return $day <= $month->daysInMonth ? $month->setDay($day) : null;
    }

    /**
     * First token that is an amount (BR-A03); "1,5 juta" and "50 rb" span two
     * tokens. Amounts of Rp 1.000 or more win over small numbers, so in
     * "beli 2 kopi 30rb" the amount is 30.000, not 2.
     *
     * @param  list<string>  $words
     * @param  array<int, bool>  $used
     */
    private static function extractAmount(array $words, array &$used): ?int
    {
        foreach ([1_000, 1] as $minimum) {
            foreach ($words as $i => $word) {
                if ($used[$i] || ! preg_match('/\d/', $word)) {
                    continue;
                }

                $next = $words[$i + 1] ?? null;
                $withUnit = $next !== null && ! $used[$i + 1] && in_array($next, self::AMOUNT_UNITS, true)
                    ? AmountParser::parse($word.$next)
                    : null;

                if ($withUnit !== null && $withUnit >= $minimum) {
                    $used[$i] = $used[$i + 1] = true;

                    return $withUnit;
                }

                $amount = AmountParser::parse($word);
                if ($amount !== null && $amount >= $minimum) {
                    $used[$i] = true;

                    return $amount;
                }
            }
        }

        return null;
    }

    /**
     * Exact name first, then a name starting with the hint, then containing it.
     */
    private static function findWallet(string $hint, ParserVocabulary $vocabulary, bool $exact = false): ?int
    {
        $hint = mb_strtolower(trim($hint));
        if ($hint === '') {
            return null;
        }

        $names = array_map(fn (string $name): string => mb_strtolower($name), $vocabulary->wallets);

        if (($id = array_search($hint, $names, true)) !== false) {
            return $id;
        }

        if ($exact || mb_strlen($hint) < 2) {
            return null;
        }

        foreach ([fn (string $n) => str_starts_with($n, $hint), fn (string $n) => str_contains($n, $hint)] as $match) {
            foreach ($names as $id => $name) {
                if ($match($name)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * BR-E05: explicit "!name" wins, then the longest matching keyword.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private static function guessCategory(TransactionType $type, ?string $hint, ?string $note, ParserVocabulary $vocabulary): array
    {
        $categoryType = $type === TransactionType::Income ? CategoryType::Income : CategoryType::Expense;
        $candidates = array_values(array_filter($vocabulary->categories, fn (array $c): bool => $c['type'] === $categoryType));

        if ($hint !== null && $hint !== '') {
            foreach ([
                fn (array $c): bool => mb_strtolower($c['name']) === $hint,
                fn (array $c): bool => in_array($hint, array_map('mb_strtolower', $c['keywords']), true),
                fn (array $c): bool => str_starts_with(mb_strtolower($c['name']), $hint),
                fn (array $c): bool => str_contains(mb_strtolower($c['name']), $hint),
            ] as $match) {
                foreach ($candidates as $category) {
                    if ($match($category)) {
                        return [$category['id'], ParsedLine::CATEGORY_EXPLICIT];
                    }
                }
            }
        }

        if ($note === null) {
            return [null, null];
        }

        $text = ' '.mb_strtolower($note).' ';
        $best = null;
        $bestLength = 0;

        foreach ($candidates as $category) {
            foreach ($category['keywords'] as $keyword) {
                $keyword = mb_strtolower(trim($keyword));
                $length = mb_strlen($keyword);

                if ($length > $bestLength && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/u', $text)) {
                    $best = $category['id'];
                    $bestLength = $length;
                }
            }
        }

        return [$best, $best !== null ? ParsedLine::CATEGORY_KEYWORD : null];
    }
}
