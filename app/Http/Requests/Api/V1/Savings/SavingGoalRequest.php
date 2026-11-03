<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Savings;

use App\Http\Requests\Concerns\SavingGoalRules;
use Illuminate\Foundation\Http\FormRequest;

class SavingGoalRequest extends FormRequest
{
    use SavingGoalRules;
}
