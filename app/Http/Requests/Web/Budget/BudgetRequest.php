<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Budget;

use App\Http\Requests\Concerns\BudgetRules;
use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    use BudgetRules;
}
