<?php

declare(strict_types=1);

namespace App\DTOs\Family;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class FamilyData extends Data
{
    public function __construct(
        public string $name,
        public int $periodStartDay = 1,
        public string $timezone = 'Asia/Jakarta',
    ) {}
}
