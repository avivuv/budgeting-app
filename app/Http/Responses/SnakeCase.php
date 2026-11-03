<?php

declare(strict_types=1);

namespace App\Http\Responses;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * Turns service payloads (spatie/laravel-data DTOs shared with the legacy
 * Inertia pages, camelCase) into REST API JSON with snake_case keys
 * (docs/api-contracts/README.md). List keys and values are left alone.
 */
final class SnakeCase
{
    public static function keys(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[is_string($key) ? Str::snake($key) : $key] = self::keys($item);
        }

        return $result;
    }
}
