<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;

/**
 * @extends BaseRepository<Tag>
 */
class TagRepository extends BaseRepository implements TagRepositoryInterface
{
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }

    public function idsForNames(array $names): array
    {
        return array_map(
            fn (string $name): int => $this->query()->firstOrCreate(['name' => $name])->id,
            $names,
        );
    }
}
