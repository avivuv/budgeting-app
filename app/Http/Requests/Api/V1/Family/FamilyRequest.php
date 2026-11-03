<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use App\Http\Requests\Concerns\FamilyRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /families (docs/api-contracts/onboarding.md).
 */
class FamilyRequest extends FormRequest
{
    use FamilyRules;
}
