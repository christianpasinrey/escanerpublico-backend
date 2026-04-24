<?php

namespace Modules\Contracts\Services;

use App\Models\Address;
use App\Models\Contact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Contracts\Jobs\PurgeContractUrls;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractDocument;
use Modules\Contracts\Models\ContractLot;
use Modules\Contracts\Models\ContractModification;
use Modules\Contracts\Models\ContractNotice;
use Modules\Contracts\Models\ContractSnapshot;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\Cache\ContractCacheInvalidator;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;

class ContractIngestor
{
    public function __construct(
        private EntityResolver $resolver,
        private ContractCacheInvalidator $invalidator,
    ) {}

    public function handleTombstone(TombstoneDTO $t): void
    {
        $c = Contract::where('external_id', $t->ref)->first();
        if (! $c) {
            return;
        }

        $c->update([
            'status_code' => 'ANUL',
            'annulled_at' => $t->when,
            'snapshot_updated_at' => $t->when,
        ]);

        $this->invalidator->invalidateContract((int) $c->id);
        PurgeContractUrls::dispatch([(int) $c->id]);
    }

    /**
     * @param  EntryDTO[]  $entries
     */
    public function ingestBatch(array $entries): BatchResult
    {
        if (empty($entries)) {
            return new BatchResult(0, 0, 0);
        }

        $this->resolver->preload();

        $processed = 0;
        $skipped = 0;
        $errored = 0;
        /** @var int[] $invalidatedContractIds */
        $invalidatedContractIds = [];

        // Pre-load existing snapshot_updated_at for recency check
        $externalIds = array_map(fn (EntryDTO $e) => $e->external_id, $entries);
        /** @var array<string, mixed> $existing */
        $existing = Contract::whereIn('external_id', $externalIds)
            ->pluck('snapshot_updated_at', 'external_id')
            ->toArray();

        DB::transaction(function () use ($entries, $existing, &$processed, &$skipped, &$errored, &$invalidatedContractIds): void {
            // ── Pass 1: Bulk-resolve orgs + companies ──

            /** @var array<string, array<string, mixed>> $newOrgs */
            $newOrgs = [];
            /** @var array<string, array<string, mixed>> $newOrgMeta */
            $newOrgMeta = [];
            /** @var array<string, array<string, mixed>> $newCompanies */
            $newCompanies = [];

            foreach ($entries as $e) {
                if ($this->isOlder($e, $existing)) {
                    continue;
                }

                $org = $e->organization;
                if ($this->resolver->resolveOrganizationId($org->dir3, $org->nif, $org->name) === null && $org->name !== '') {
                    $truncatedName = $this->truncate($org->name, 255);
                    $key = md5(($org->dir3 ?? '').'|'.$truncatedName);
                    if (! isset($newOrgs[$key])) {
                        $newOrgs[$key] = [
                            'name' => $truncatedName,
                            'identifier' => $this->truncate($org->dir3, 255),
                            'nif' => $org->nif,
                            'platform_id' => $org->platform_id,
                            'buyer_profile_uri' => $this->truncate($org->buyer_profile_uri, 500),
                            'activity_code' => $org->activity_code,
                            'type_code' => $org->type_code,
                            'hierarchy' => $org->hierarchy !== [] ? json_encode($org->hierarchy) : null,
                            'parent_name' => $this->truncate($org->hierarchy[0] ?? null, 255),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        $newOrgMeta[$key] = [
                            '_address' => $org->address,
                            '_contacts' => $org->contacts,
                            'dir3' => $this->truncate($org->dir3, 255),
                            'nif' => $org->nif,
                            'name' => $truncatedName,
                        ];
                    }
                }

                foreach ($e->results as $r) {
                    if ($r->winner === null) {
                        continue;
                    }
                    if ($this->resolver->resolveCompanyId($r->winner->nif, $r->winner->name) === null) {
                        $truncatedWinnerName = $this->truncate($r->winner->name, 255);
                        $key = md5($r->winner->nif ?? 'name:'.$truncatedWinnerName);
                        if (! isset($newCompanies[$key])) {
                            $newCompanies[$key] = [
                                'name' => $truncatedWinnerName,
                                'identifier' => $this->truncate($r->winner->nif, 255),
                                'nif' => $r->winner->nif,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }

            if ($newOrgs !== []) {
                Organization::insertOrIgnore(array_values($newOrgs));
                $names = array_column($newOrgs, 'name');
                foreach (Organization::whereIn('name', $names)->get() as $o) {
                    $this->resolver->registerOrganization($o);
                }
                foreach ($newOrgMeta as $meta) {
                    $orgId = $this->resolver->resolveOrganizationId($meta['dir3'], $meta['nif'], $meta['name']);
                    if ($orgId === null) {
                        continue;
                    }
                    $addr = $meta['_address'];
                    if ($addr !== null) {
                        Address::updateOrCreate(
                            ['addressable_type' => Organization::class, 'addressable_id' => $orgId],
                            [
                                'line' => $addr->line,
                                'postal_code' => $addr->postal_code,
                            ],
                        );
                    }
                    if (! empty($meta['_contacts'])) {
                        Contact::where('contactable_type', Organization::class)
                            ->where('contactable_id', $orgId)
                            ->delete();
                        foreach ($meta['_contacts'] as $c) {
                            Contact::create([
                                'contactable_type' => Organization::class,
                                'contactable_id' => $orgId,
                                'type' => $c->type,
                                'value' => $c->value,
                            ]);
                        }
                    }
                }
            }

            if ($newCompanies !== []) {
                Company::insertOrIgnore(array_values($newCompanies));
                $names = array_column($newCompanies, 'name');
                foreach (Company::whereIn('name', $names)->get() as $c) {
                    $this->resolver->registerCompany($c);
                }
            }

            // ── Pass 2: Upsert contracts ──

            /** @var array<int, array<string, mixed>> $contractsRows */
            $contractsRows = [];
            foreach ($entries as $e) {
                if ($this->isOlder($e, $existing)) {
                    $skipped++;

                    continue;
                }

                $orgId = $this->resolver->resolveOrganizationId(
                    $this->truncate($e->organization->dir3, 255),
                    $e->organization->nif,
                    $this->truncate($e->organization->name, 255),
                );
                $firstLot = $e->lots[0] ?? null;

                $contractsRows[] = [
                    'external_id' => $this->truncate($e->external_id, 500),
                    'expediente' => $this->truncate($e->expediente, 500),
                    'link' => $this->truncate($e->link, 1000),
                    'buyer_profile_uri' => $this->truncate($e->organization->buyer_profile_uri, 500),
                    'activity_code' => $e->organization->activity_code,
                    'status_code' => $e->status_code,
                    'objeto' => $firstLot?->title,
                    'tipo_contrato_code' => $firstLot?->tipo_contrato_code,
                    'subtipo_contrato_code' => $firstLot?->subtipo_contrato_code,
                    'importe_sin_iva' => $firstLot?->budget_without_tax,
                    'importe_con_iva' => $firstLot?->budget_with_tax,
                    'valor_estimado' => $firstLot?->estimated_value,
                    'procedimiento_code' => $e->process?->procedure_code,
                    'urgencia_code' => $e->process?->urgency_code,
                    'cpv_codes' => $firstLot !== null ? json_encode($firstLot->cpv_codes) : null,
                    'nuts_code' => $firstLot?->nuts_code,
                    'lugar_ejecucion' => $this->truncate($firstLot?->lugar_ejecucion, 500),
                    'fecha_presentacion_limite' => $e->process?->fecha_presentacion_limite,
                    'duracion' => $firstLot?->duration,
                    'duracion_unidad' => $firstLot?->duration_unit,
                    'fecha_inicio' => $firstLot?->start_date,
                    'fecha_fin' => $firstLot?->end_date,
                    'submission_method_code' => $e->process?->submission_method_code,
                    'contracting_system_code' => $e->process?->contracting_system_code,
                    'fecha_disponibilidad_docs' => $e->process?->fecha_disponibilidad_docs,
                    'hora_presentacion_limite' => $e->process?->hora_presentacion_limite,
                    'garantia_tipo_code' => $e->terms?->guarantee_type_code,
                    'garantia_porcentaje' => $e->terms?->guarantee_percentage,
                    'idioma' => $e->terms?->language,
                    'opciones_descripcion' => $firstLot?->options_description,
                    'mix_contract_indicator' => null,
                    'funding_program_code' => $e->terms?->funding_program_code,
                    'over_threshold_indicator' => $e->terms?->over_threshold_indicator,
                    'national_legislation_code' => $e->terms?->national_legislation_code,
                    'received_appeal_quantity' => $e->terms?->received_appeal_quantity,
                    'organization_id' => $orgId,
                    'snapshot_updated_at' => $e->entry_updated_at->format('Y-m-d H:i:s'),
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($contractsRows !== []) {
                // Dedupe by external_id keeping the row with the MAX snapshot_updated_at.
                // Otherwise if the atom yields duplicates in oldest-first order,
                // the stored snapshot_updated_at would be the older one and the
                // next re-ingest would re-process the newer row as "new".
                $byExternal = [];
                foreach ($contractsRows as $row) {
                    $ext = $row['external_id'];
                    if (! isset($byExternal[$ext]) || $row['snapshot_updated_at'] > $byExternal[$ext]['snapshot_updated_at']) {
                        $byExternal[$ext] = $row;
                    }
                }
                $contractsRows = array_values($byExternal);
                Contract::upsert($contractsRows, ['external_id'], [
                    'expediente', 'link', 'buyer_profile_uri', 'activity_code', 'status_code', 'objeto',
                    'tipo_contrato_code', 'subtipo_contrato_code', 'importe_sin_iva', 'importe_con_iva',
                    'valor_estimado', 'procedimiento_code', 'urgencia_code', 'cpv_codes', 'nuts_code', 'lugar_ejecucion',
                    'fecha_presentacion_limite', 'duracion', 'duracion_unidad', 'fecha_inicio', 'fecha_fin',
                    'submission_method_code', 'contracting_system_code', 'fecha_disponibilidad_docs', 'hora_presentacion_limite',
                    'garantia_tipo_code', 'garantia_porcentaje', 'idioma', 'opciones_descripcion', 'mix_contract_indicator',
                    'funding_program_code', 'over_threshold_indicator', 'national_legislation_code', 'received_appeal_quantity',
                    'organization_id', 'snapshot_updated_at', 'synced_at', 'updated_at',
                ]);
            }

            // Resolve contract IDs
            $contractIds = Contract::whereIn('external_id', array_column($contractsRows, 'external_id'))
                ->pluck('id', 'external_id')->toArray();

            // ── Pass 3: Lots + Awards + Criteria + Notices + Documents + Modifications + Snapshot ──

            foreach ($entries as $e) {
                if ($this->isOlder($e, $existing)) {
                    continue;
                }

                $contractId = $contractIds[$e->external_id] ?? null;
                if ($contractId === null) {
                    continue;
                }
                $invalidatedContractIds[] = (int) $contractId;

                // Lots
                $lotRows = [];
                foreach ($e->lots as $lot) {
                    $lotRows[] = [
                        'contract_id' => $contractId,
                        'lot_number' => $lot->lot_number,
                        'title' => $this->truncate($lot->title, 500),
                        'description' => $lot->description,
                        'tipo_contrato_code' => $lot->tipo_contrato_code,
                        'subtipo_contrato_code' => $lot->subtipo_contrato_code,
                        'cpv_codes' => json_encode($lot->cpv_codes),
                        'budget_with_tax' => $lot->budget_with_tax,
                        'budget_without_tax' => $lot->budget_without_tax,
                        'estimated_value' => $lot->estimated_value,
                        'duration' => $lot->duration,
                        'duration_unit' => $lot->duration_unit,
                        'start_date' => $lot->start_date,
                        'end_date' => $lot->end_date,
                        'nuts_code' => $lot->nuts_code,
                        'lugar_ejecucion' => $this->truncate($lot->lugar_ejecucion, 255),
                        'options_description' => $lot->options_description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($lotRows !== []) {
                    ContractLot::upsert($lotRows, ['contract_id', 'lot_number'], [
                        'title', 'description', 'tipo_contrato_code', 'subtipo_contrato_code', 'cpv_codes',
                        'budget_with_tax', 'budget_without_tax', 'estimated_value', 'duration', 'duration_unit',
                        'start_date', 'end_date', 'nuts_code', 'lugar_ejecucion', 'options_description', 'updated_at',
                    ]);
                }

                $lotIds = ContractLot::where('contract_id', $contractId)->pluck('id', 'lot_number')->toArray();

                // Awards
                $awardRows = [];
                foreach ($e->results as $r) {
                    if ($r->winner === null) {
                        continue;
                    }
                    $lotId = $lotIds[$r->lot_number] ?? ($lotIds[1] ?? null);
                    if ($lotId === null) {
                        continue;
                    }
                    $companyId = $this->resolver->resolveCompanyId($r->winner->nif, $this->truncate($r->winner->name, 255));
                    if ($companyId === null) {
                        continue;
                    }

                    if ($r->winner->address !== null) {
                        Address::updateOrCreate(
                            ['addressable_type' => Company::class, 'addressable_id' => $companyId],
                            [
                                'line' => $r->winner->address->line,
                                'postal_code' => $r->winner->address->postal_code,
                            ],
                        );
                    }

                    $awardRows[] = [
                        'contract_lot_id' => $lotId,
                        'company_id' => $companyId,
                        'amount' => $r->amount_with_tax,
                        'amount_without_tax' => $r->amount_without_tax,
                        'description' => $r->description,
                        'procedure_type' => $e->process?->procedure_code,
                        'urgency' => $e->process?->urgency_code,
                        'award_date' => $r->award_date,
                        'start_date' => $r->start_date,
                        'formalization_date' => $r->formalization_date,
                        'contract_number' => $r->contract_number,
                        'sme_awarded' => $r->sme_awarded,
                        'num_offers' => $r->num_offers,
                        'smes_received_tender_quantity' => $r->smes_received_tender_quantity,
                        'result_code' => $r->result_code,
                        'lower_tender_amount' => $r->lower_tender_amount,
                        'higher_tender_amount' => $r->higher_tender_amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($awardRows !== []) {
                    Award::upsert($awardRows, ['contract_lot_id', 'company_id'], [
                        'amount', 'amount_without_tax', 'description', 'procedure_type', 'urgency',
                        'award_date', 'start_date', 'formalization_date', 'contract_number', 'sme_awarded',
                        'num_offers', 'smes_received_tender_quantity', 'result_code',
                        'lower_tender_amount', 'higher_tender_amount', 'updated_at',
                    ]);
                }

                // Criteria
                $critRows = [];
                foreach ($e->criteria_by_lot as $lotNumber => $crits) {
                    $lotId = $lotIds[$lotNumber] ?? ($lotIds[1] ?? null);
                    if ($lotId === null) {
                        continue;
                    }
                    foreach ($crits as $c) {
                        $critRows[] = [
                            'contract_lot_id' => $lotId,
                            'type_code' => $c->type_code,
                            'subtype_code' => $c->subtype_code,
                            'description' => $c->description,
                            'note' => $c->note,
                            'weight_numeric' => $c->weight_numeric,
                            'sort_order' => $c->sort_order,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if ($critRows !== []) {
                    AwardingCriterion::upsert($critRows, ['contract_lot_id', 'sort_order'], [
                        'type_code', 'subtype_code', 'description', 'note', 'weight_numeric', 'updated_at',
                    ]);
                }

                // Notices (skip ones without issue_date since it's part of unique key)
                $noticeRows = [];
                foreach ($e->notices as $n) {
                    if ($n->issue_date === '' || $n->issue_date === null) {
                        continue;
                    }
                    $noticeRows[] = [
                        'contract_id' => $contractId,
                        'notice_type_code' => $n->notice_type_code,
                        'publication_media' => $n->publication_media,
                        'issue_date' => $n->issue_date,
                        'document_uri' => $n->document_uri,
                        'document_filename' => $n->document_filename,
                        'document_type_code' => $n->document_type_code,
                        'document_type_name' => $n->document_type_name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($noticeRows !== []) {
                    // Dedupe by unique key in case of XML duplicates
                    $noticeRows = array_values(collect($noticeRows)->keyBy(
                        fn ($r) => $r['contract_id'].'|'.$r['notice_type_code'].'|'.$r['issue_date']
                    )->all());
                    ContractNotice::upsert($noticeRows, ['contract_id', 'notice_type_code', 'issue_date'], [
                        'publication_media', 'document_uri', 'document_filename', 'document_type_code', 'document_type_name', 'updated_at',
                    ]);
                }

                // Documents
                $docRows = [];
                foreach ($e->documents as $d) {
                    if ($d->uri === null || $d->uri === '') {
                        continue;
                    }
                    $docRows[] = [
                        'contract_id' => $contractId,
                        'type' => $d->type,
                        'name' => $d->name,
                        'uri' => $d->uri,
                        'hash' => $d->hash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($docRows !== []) {
                    $docRows = array_values(collect($docRows)->keyBy(
                        fn ($r) => $r['contract_id'].'|'.$r['uri']
                    )->all());
                    ContractDocument::upsert($docRows, ['contract_id', 'uri'], [
                        'type', 'name', 'hash', 'updated_at',
                    ]);
                }

                // Modifications from DOC_MOD / DOC_PRI / DOC_DES / DOC_REN / DOC_ANUL notices
                $modRows = [];
                foreach ($e->notices as $n) {
                    if ($n->issue_date === '' || $n->issue_date === null) {
                        continue;
                    }
                    $type = match ($n->notice_type_code) {
                        'DOC_MOD' => 'modification',
                        'DOC_PRI' => 'extension',
                        'DOC_DES', 'DOC_REN' => 'cancellation',
                        'DOC_ANUL' => 'annulment',
                        default => null,
                    };
                    if ($type === null) {
                        continue;
                    }
                    $modRows[] = [
                        'contract_id' => $contractId,
                        'type' => $type,
                        'issue_date' => $n->issue_date,
                        'description' => null,
                        'amount_delta' => null,
                        'new_end_date' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($modRows !== []) {
                    $modRows = array_values(collect($modRows)->keyBy(
                        fn ($r) => $r['contract_id'].'|'.$r['type'].'|'.$r['issue_date']
                    )->all());
                    ContractModification::upsert($modRows, ['contract_id', 'type', 'issue_date'], [
                        'description', 'amount_delta', 'new_end_date', 'updated_at',
                    ]);
                }

                // Snapshot capture
                $payload = [
                    'external_id' => $e->external_id,
                    'expediente' => $e->expediente,
                    'status_code' => $e->status_code,
                    'lots_count' => count($e->lots),
                    'results_count' => count($e->results),
                    'notices_count' => count($e->notices),
                ];
                ksort($payload);
                $hash = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');

                ContractSnapshot::insertOrIgnore([[
                    'contract_id' => $contractId,
                    'entry_updated_at' => $e->entry_updated_at->format('Y-m-d H:i:s'),
                    'status_code' => $e->status_code,
                    'content_hash' => $hash,
                    'payload' => json_encode($payload),
                    'ingested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]]);

                $processed++;
            }

            $this->resolver->persistCaches();
        });

        // Post-commit cache invalidation + CF purge
        $invalidatedContractIds = array_values(array_unique($invalidatedContractIds));
        foreach ($invalidatedContractIds as $id) {
            $this->invalidator->invalidateContract($id);
        }
        $this->invalidator->invalidateListings();
        if ($invalidatedContractIds !== []) {
            PurgeContractUrls::dispatch($invalidatedContractIds);
        }

        return new BatchResult($processed, $skipped, $errored);
    }

    /**
     * Returns true if the existing DB snapshot_updated_at is >= entry.updated_at.
     * Compares at second precision because MySQL TIMESTAMP stores only whole
     * seconds, so the in-memory DateTimeImmutable's microseconds would make
     * every re-ingest look newer than the stored value.
     *
     * @param  array<string, mixed>  $existing
     */
    private function isOlder(EntryDTO $e, array $existing): bool
    {
        $ex = $existing[$e->external_id] ?? null;
        if ($ex === null) {
            return false;
        }

        $exDt = Carbon::parse($ex)->startOfSecond()->toDateTimeImmutable();
        $entryDt = Carbon::instance(\DateTime::createFromImmutable($e->entry_updated_at))
            ->startOfSecond()
            ->toDateTimeImmutable();

        return $entryDt <= $exDt;
    }

    /**
     * Truncate a string to fit in a VARCHAR column (multi-byte safe).
     * Returns null if the input is null.
     */
    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max, 'UTF-8');
    }
}
