<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Savings;

use App\Http\Requests\Concerns\ContributionRules;
use Illuminate\Foundation\Http\FormRequest;

class ContributeRequest extends FormRequest
{
    use ContributionRules;
}
