<?php

namespace Modules\Borme\DTOs;

use Modules\Borme\Enums\ActType;
use Modules\Borme\Enums\LegalForm;

class BormeEntryDTO
{
    /**
     * @param  ActType[]  $actTypes
     * @param  ActItemDTO[]  $actItems
     * @param  OfficerDTO[]  $officers
     */
    public function __construct(
        public readonly int $entryNumber,
        public readonly string $companyNameRaw,
        public readonly string $companyNameNormalized,
        public readonly ?LegalForm $legalForm,
        public readonly ?string $registryLetter,
        public readonly ?int $registrySheet,
        public readonly ?string $registrySection,
        public readonly ?string $registryInscription,
        public readonly ?string $registryDate,
        public readonly array $actTypes,
        public readonly array $actItems,
        public readonly array $officers,
        public readonly string $rawText,
    ) {}

    public function toArray(): array
    {
        return [
            'entry_number' => $this->entryNumber,
            'company_name_raw' => $this->companyNameRaw,
            'company_name_normalized' => $this->companyNameNormalized,
            'legal_form' => $this->legalForm?->value,
            'registry' => [
                'letter' => $this->registryLetter,
                'sheet' => $this->registrySheet,
                'section' => $this->registrySection,
                'inscription' => $this->registryInscription,
                'date' => $this->registryDate,
            ],
            'act_types' => array_map(fn (ActType $t) => $t->value, $this->actTypes),
            'act_items' => array_map(fn (ActItemDTO $i) => $i->toArray(), $this->actItems),
            'officers' => array_map(fn (OfficerDTO $o) => $o->toArray(), $this->officers),
            'raw_text' => $this->rawText,
        ];
    }
}
