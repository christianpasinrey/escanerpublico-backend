<?php

namespace Modules\Borme\Services;

use Modules\Borme\DTOs\BormeEntryDTO;
use Modules\Borme\DTOs\OfficerDTO;
use Modules\Borme\Models\Person;
use Modules\Borme\Services\Support\NameNormalizer;
use Modules\Contracts\Models\Company;

class EntityResolver
{
    public function __construct(private readonly NameNormalizer $nameNormalizer) {}

    /**
     * Resolve / create the Company referenced by a BORME entry. Resolution order:
     *   1. (registry_letter, registry_sheet) — most reliable, mapped to the
     *      mercantile registry sheet which is the natural PK in BORME.
     *   2. name_normalized exact match — backstop for entries without registry data.
     *   3. Otherwise create a new company seeded from the entry.
     *
     * @return array{company: Company, status: 'matched'|'created'}
     */
    public function resolveCompany(BormeEntryDTO $entry): array
    {
        if ($entry->registryLetter !== null && $entry->registrySheet !== null) {
            $byRegistry = Company::query()
                ->where('registry_letter', $entry->registryLetter)
                ->where('registry_sheet', $entry->registrySheet)
                ->first();
            if ($byRegistry !== null) {
                return ['company' => $this->backfill($byRegistry, $entry), 'status' => 'matched'];
            }
        }

        if ($entry->companyNameNormalized !== '') {
            $byName = Company::query()
                ->where('name_normalized', $entry->companyNameNormalized)
                ->whereNull('registry_sheet') // don't hijack a registry-bound company by name alone
                ->first();
            if ($byName !== null) {
                return ['company' => $this->backfill($byName, $entry), 'status' => 'matched'];
            }
        }

        $created = Company::create([
            'name' => $entry->companyNameRaw,
            'name_normalized' => $entry->companyNameNormalized,
            'legal_form' => $entry->legalForm?->value,
            'registry_letter' => $entry->registryLetter,
            'registry_sheet' => $entry->registrySheet,
            'registry_section' => $entry->registrySection,
            'source_modules' => ['borme'],
        ]);

        return ['company' => $created, 'status' => 'created'];
    }

    /**
     * Resolve a Person reference (officer name) to a stored row, creating it on
     * miss. Match key is the normalized full name.
     */
    public function resolvePerson(string $rawName): Person
    {
        $normalized = $this->nameNormalizer->normalize($rawName);

        return Person::firstOrCreate(
            ['full_name_normalized' => $normalized],
            ['full_name_raw' => $rawName]
        );
    }

    /**
     * Resolve the entity referenced as officer (person or company).
     *
     * @return array{kind: 'person'|'company', person?: Person, company?: Company}
     */
    public function resolveOfficer(OfficerDTO $officer): array
    {
        if ($officer->kind === 'company') {
            // Company-as-officer: look up by normalized name; if absent, create.
            $byName = Company::query()
                ->where('name_normalized', $officer->nameNormalized)
                ->first();
            if ($byName === null) {
                $byName = Company::create([
                    'name' => $officer->nameRaw,
                    'name_normalized' => $officer->nameNormalized,
                    'source_modules' => ['borme'],
                ]);
            }

            return ['kind' => 'company', 'company' => $byName];
        }

        return ['kind' => 'person', 'person' => $this->resolvePerson($officer->nameRaw)];
    }

    /**
     * Fill in missing registry fields on a previously-known company. Existing
     * non-null values win — BORME data only adds, never overwrites.
     */
    private function backfill(Company $company, BormeEntryDTO $entry): Company
    {
        $updates = [];
        if ($company->registry_letter === null && $entry->registryLetter !== null) {
            $updates['registry_letter'] = $entry->registryLetter;
        }
        if ($company->registry_sheet === null && $entry->registrySheet !== null) {
            $updates['registry_sheet'] = $entry->registrySheet;
        }
        if ($company->registry_section === null && $entry->registrySection !== null) {
            $updates['registry_section'] = $entry->registrySection;
        }
        if (empty($company->legal_form) && $entry->legalForm !== null) {
            $updates['legal_form'] = $entry->legalForm->value;
        }
        if (empty($company->name_normalized)) {
            $updates['name_normalized'] = $entry->companyNameNormalized;
        }

        $sources = $company->source_modules ?? [];
        if (! in_array('borme', $sources, true)) {
            $sources[] = 'borme';
            $updates['source_modules'] = $sources;
        }

        if ($updates !== []) {
            $company->update($updates);
            $company->refresh();
        }

        return $company;
    }
}
