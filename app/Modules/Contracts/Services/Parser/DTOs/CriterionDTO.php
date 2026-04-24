<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class CriterionDTO
{
    public function __construct(
        public int $lot_number,
        public string $type_code,
        public ?string $subtype_code,
        public string $description,
        public ?string $note,
        public ?float $weight_numeric,
        public int $sort_order,
    ) {}
}
