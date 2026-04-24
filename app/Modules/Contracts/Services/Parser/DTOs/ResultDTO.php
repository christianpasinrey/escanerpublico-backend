<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ResultDTO
{
    public function __construct(
        public int $lot_number,
        public ?WinningPartyDTO $winner,
        public ?float $amount_with_tax,
        public ?float $amount_without_tax,
        public ?float $lower_tender_amount,
        public ?float $higher_tender_amount,
        public ?int $num_offers,
        public ?int $smes_received_tender_quantity,
        public ?bool $sme_awarded,
        public ?string $award_date,
        public ?string $start_date,
        public ?string $formalization_date,
        public ?string $contract_number,
        public ?string $result_code,
        public ?string $description,
    ) {}
}
