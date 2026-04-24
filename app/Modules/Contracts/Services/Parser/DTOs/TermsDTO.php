<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class TermsDTO
{
    public function __construct(
        public ?string $language,
        public ?string $funding_program_code,
        public ?string $national_legislation_code,
        public ?bool $over_threshold_indicator,
        public ?int $received_appeal_quantity,
        public ?bool $variant_constraint_indicator,
        public ?bool $required_curricula_indicator,
        public ?string $guarantee_type_code,
        public ?float $guarantee_percentage,
    ) {}
}
