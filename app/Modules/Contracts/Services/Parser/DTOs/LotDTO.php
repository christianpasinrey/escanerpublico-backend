<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class LotDTO
{
    /** @param  string[]  $cpv_codes */
    public function __construct(
        public int $lot_number,
        public ?string $title,
        public ?string $description,
        public ?string $tipo_contrato_code,
        public ?string $subtipo_contrato_code,
        public array $cpv_codes,
        public ?float $budget_with_tax,
        public ?float $budget_without_tax,
        public ?float $estimated_value,
        public ?float $duration,
        public ?string $duration_unit,
        public ?string $start_date,
        public ?string $end_date,
        public ?string $nuts_code,
        public ?string $lugar_ejecucion,
        public ?string $options_description,
    ) {}
}
