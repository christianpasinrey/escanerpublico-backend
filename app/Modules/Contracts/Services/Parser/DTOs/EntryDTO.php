<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class EntryDTO
{
    /**
     * @param  LotDTO[]  $lots
     * @param  ResultDTO[]  $results
     * @param  array<int, CriterionDTO[]>  $criteria_by_lot
     * @param  NoticeDTO[]  $notices
     * @param  DocumentDTO[]  $documents
     */
    public function __construct(
        public string $external_id,
        public ?string $link,
        public string $expediente,
        public string $status_code,
        public \DateTimeImmutable $entry_updated_at,
        public OrganizationDTO $organization,
        public array $lots,
        public ?ProcessDTO $process,
        public array $results,
        public ?TermsDTO $terms,
        public array $criteria_by_lot,
        public array $notices,
        public array $documents,
    ) {}
}
