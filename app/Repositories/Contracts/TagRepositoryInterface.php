<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tag;

/**
 * @extends BaseRepositoryInterface<Tag>
 */
interface TagRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Ids of the given tag names in the active family, creating missing ones.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public function idsForNames(array $names): array;

    /**
     * Tag names of the active family, alphabetical.
     *
     * @return list<string>
     */
    public function names(): array;
}
