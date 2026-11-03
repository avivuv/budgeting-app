<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Family;

use App\Http\Requests\Concerns\FamilyRules;
use Illuminate\Foundation\Http\FormRequest;

class FamilyRequest extends FormRequest
{
    use FamilyRules;
}
