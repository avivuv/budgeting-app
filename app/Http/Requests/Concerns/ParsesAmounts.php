<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts amounts typed as "50rb", "1,5jt" or "50.000" and turns them into whole
 * rupiah before validation (BR-A03). Unparseable input is left for the
 * "integer" rule to reject.
 *
 * @mixin FormRequest
 */
trait ParsesAmounts
{
    /**
     * @param  list<string>  $fields
     */
    protected function parseAmounts(array $fields): void
    {
        $parsed = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '') {
                $parsed[$field] = AmountParser::parse($value) ?? $value;
            }
        }

        $this->merge($parsed);
    }
}
