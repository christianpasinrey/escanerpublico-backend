<?php

namespace Modules\Borme\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Borme\DTOs\BormeEntryDTO;
use Modules\Borme\DTOs\OfficerDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Models\BormeActItem;
use Modules\Borme\Models\BormeEntry;
use Modules\Borme\Models\BormeOfficer;
use Modules\Borme\Models\BormePdf;
use Modules\Borme\Services\Parsers\SectionOneParser;

class PersistBormeEntry
{
    public function __construct(private readonly EntityResolver $resolver) {}

    /**
     * Persist a single parsed entry with its act items and officers, idempotently
     * keyed by (borme_pdf_id, entry_number). Wraps the company resolution and
     * derived company-state updates in a single transaction.
     */
    public function persist(BormePdf $pdf, BormeEntryDTO $dto): BormeEntry
    {
        return DB::transaction(function () use ($pdf, $dto) {
            $resolved = $this->resolver->resolveCompany($dto);
            $company = $resolved['company'];

            $entry = BormeEntry::updateOrCreate(
                [
                    'borme_pdf_id' => $pdf->id,
                    'entry_number' => $dto->entryNumber,
                ],
                [
                    'company_id' => $company->id,
                    'company_name_raw' => $dto->companyNameRaw,
                    'company_name_normalized' => $dto->companyNameNormalized,
                    'legal_form' => $dto->legalForm?->value,
                    'registry_letter' => $dto->registryLetter,
                    'registry_sheet' => $dto->registrySheet,
                    'registry_section' => $dto->registrySection,
                    'registry_inscription' => $dto->registryInscription,
                    'registry_date' => $dto->registryDate,
                    'act_types' => array_map(fn (ActType $t) => $t->value, $dto->actTypes),
                    'parser_version' => SectionOneParser::PARSER_VERSION,
                    'parsed_at' => now(),
                    'raw_text' => $dto->rawText,
                    'resolution_status' => $resolved['status'] === 'matched' ? 'matched' : 'created',
                ]
            );

            // act_items: replace on re-parse to keep state coherent with parser_version.
            BormeActItem::where('borme_entry_id', $entry->id)->delete();
            foreach ($dto->actItems as $item) {
                BormeActItem::create([
                    'borme_entry_id' => $entry->id,
                    'act_type' => $item->actType->value,
                    'payload' => $item->payload,
                    'effective_date' => $item->effectiveDate,
                ]);
            }

            BormeOfficer::where('borme_entry_id', $entry->id)->delete();
            foreach ($dto->officers as $officer) {
                $this->persistOfficer($entry, $officer);
            }

            $this->applyCompanySideEffects($company, $dto);

            return $entry;
        });
    }

    private function persistOfficer(BormeEntry $entry, OfficerDTO $officer): void
    {
        $resolved = $this->resolver->resolveOfficer($officer);
        $representative = $officer->representativeNameRaw !== null
            ? $this->resolver->resolvePerson($officer->representativeNameRaw)
            : null;

        BormeOfficer::create([
            'borme_entry_id' => $entry->id,
            'officer_kind' => $resolved['kind'],
            'officer_person_id' => $resolved['kind'] === 'person' ? $resolved['person']->id : null,
            'officer_company_id' => $resolved['kind'] === 'company' ? $resolved['company']->id : null,
            'representative_person_id' => $representative?->id,
            'role' => $officer->role->value,
            'action' => $officer->action->value,
            'effective_date' => null,
        ]);
    }

    /**
     * Promote selected facts from the entry into the company row so the
     * /companies index can show current state without joining BORME tables.
     * Only fills nulls and never downgrades a known status.
     */
    private function applyCompanySideEffects($company, BormeEntryDTO $dto): void
    {
        $updates = [];

        $registryDate = $dto->registryDate !== null ? Carbon::parse($dto->registryDate) : null;
        if ($registryDate !== null && (
            $company->last_act_date === null ||
            $registryDate->greaterThan($company->last_act_date)
        )) {
            $updates['last_act_date'] = $registryDate;
        }

        foreach ($dto->actItems as $item) {
            if ($item->actType === ActType::Constitution) {
                if ($company->incorporation_date === null && $item->effectiveDate !== null) {
                    $updates['incorporation_date'] = $item->effectiveDate;
                }

                $payload = $item->payload;
                if ($company->capital_cents === null && isset($payload['capital']['amount_cents'])) {
                    $updates['capital_cents'] = $payload['capital']['amount_cents'];
                    $updates['capital_currency'] = $payload['capital']['currency'] ?? 'EUR';
                }
                if ($company->domicile_address === null && isset($payload['domicile']['address'])) {
                    $updates['domicile_address'] = $payload['domicile']['address'];
                    $updates['domicile_city'] = $payload['domicile']['city'] ?? null;
                }
                if ($company->status === null) {
                    $updates['status'] = 'active';
                }
            }
        }

        // Status transitions derived from act_types — order matters: extinction
        // wins over dissolution; concurso is a separate sticky flag.
        $types = array_map(fn (ActType $t) => $t->value, $dto->actTypes);
        if (in_array('extinction', $types, true)) {
            $updates['status'] = 'extinct';
        } elseif (in_array('dissolution', $types, true)) {
            $updates['status'] = 'dissolved';
        } elseif (in_array('concurso', $types, true) && ! in_array('extinction', $types, true)) {
            $updates['status'] = 'concurso';
        }

        if ($updates !== []) {
            $company->update($updates);
        }
    }
}
