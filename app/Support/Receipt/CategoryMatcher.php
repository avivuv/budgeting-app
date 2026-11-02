<?php

declare(strict_types=1);

namespace App\Support\Receipt;

/**
 * Maps a category name suggested by the AI (or an item name) to one of the
 * family's expense categories: exact name first, then category keywords.
 */
final class CategoryMatcher
{
    /**
     * @param  list<array{id: int, name: string, keywords: list<string>}>  $categories
     */
    public function __construct(private readonly array $categories) {}

    public function match(?string $text): ?int
    {
        $needle = self::normalize($text);

        if ($needle === '') {
            return null;
        }

        foreach ($this->categories as $category) {
            if (self::normalize($category['name']) === $needle) {
                return $category['id'];
            }
        }

        foreach ($this->categories as $category) {
            foreach ($category['keywords'] as $keyword) {
                $keyword = self::normalize($keyword);

                if ($keyword !== '' && preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $needle) === 1) {
                    return $category['id'];
                }
            }
        }

        return null;
    }

    private static function normalize(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $text)));
    }
}
