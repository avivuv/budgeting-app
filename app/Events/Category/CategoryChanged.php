<?php

declare(strict_types=1);

namespace App\Events\Category;

use App\Events\AffectsFamilyFinances;
use App\Models\Category;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A category was created, changed, archived, merged or deleted.
 */
class CategoryChanged implements AffectsFamilyFinances, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Category $category,
    ) {}

    public function familyId(): int
    {
        return $this->category->family_id;
    }
}
