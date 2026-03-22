<?php

namespace Modules\Contracts\Jobs;

use App\Models\Address;
use App\Models\Contact;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractDocument;
use Modules\Contracts\Models\ContractNotice;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\PlacspParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPlacspFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    // In-memory caches
    private array $organizationsCache = [];
    private array $companiesCache = [];

    // Redis cache key prefix and TTL (2 hours)
    private const CACHE_TAG = 'placsp_import';
    private const CACHE_TTL = 7200;

    public function __construct(
        public string $filePath,
    ) {}

    public function handle(PlacspParser $parser): void
    {
        $content = file_get_contents($this->filePath);
        if (!$content) {
            Log::error("PLACSP: No se pudo leer {$this->filePath}");
            return;
        }

        // Pre-load caches (from Redis if available, otherwise from DB)
        $this->preloadCaches();

        $contracts = $parser->parseAtomFile($content);
        $upserted = 0;

        // Process in batches of 500
        $chunks = array_chunk($contracts, 500);

        foreach ($chunks as $chunk) {
            $this->processBatch($chunk);
            $upserted += count($chunk);
        }

        Log::info("PLACSP: Procesado {$this->filePath} — {$upserted} contratos procesados");
    }

    public static function flushImportCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cache preloading
    // ─────────────────────────────────────────────────────────────────────

    private function preloadCaches(): void
    {
        // Try Redis first for organizations
        $cachedOrgs = Cache::tags([self::CACHE_TAG])->get('organizations_cache');
        if ($cachedOrgs !== null) {
            $this->organizationsCache = $cachedOrgs;
        } else {
            Organization::select('id', 'identifier', 'name', 'nif')->chunk(10000, function ($orgs) {
                foreach ($orgs as $org) {
                    if ($org->identifier) {
                        $this->organizationsCache['dir3:' . $org->identifier] = $org->id;
                    }
                    if ($org->nif) {
                        $this->organizationsCache['nif:' . $org->nif] = $org->id;
                    }
                    $key = $this->makeOrganizationKey($org->identifier, $org->name);
                    $this->organizationsCache[$key] = $org->id;
                }
            });

            Cache::tags([self::CACHE_TAG])->put('organizations_cache', $this->organizationsCache, self::CACHE_TTL);
        }

        // Try Redis first for companies
        $cachedCompanies = Cache::tags([self::CACHE_TAG])->get('companies_cache');
        if ($cachedCompanies !== null) {
            $this->companiesCache = $cachedCompanies;
        } else {
            Company::select('id', 'identifier', 'name', 'nif')->chunk(10000, function ($companies) {
                foreach ($companies as $company) {
                    if ($company->nif) {
                        $this->companiesCache['nif:' . $company->nif] = $company->id;
                    }
                    $key = $this->makeCompanyKey($company->nif, $company->name);
                    $this->companiesCache[$key] = $company->id;
                }
            });

            Cache::tags([self::CACHE_TAG])->put('companies_cache', $this->companiesCache, self::CACHE_TTL);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Batch processing (2-pass pattern)
    // ─────────────────────────────────────────────────────────────────────

    private function processBatch(array $entries): void
    {
        DB::transaction(function () use ($entries) {
            // ── Pass 1: Bulk-create unknown organizations and companies ──

            $newOrganizations = [];
            $newOrgMeta = []; // keyed same as $newOrganizations — holds _address/_contacts
            $newCompanies = [];

            foreach ($entries as $data) {
                if (empty($data['external_id']) || empty($data['expediente'])) {
                    continue;
                }

                // Collect unknown organizations
                $orgData = $data['_organization'] ?? [];
                $orgName = $orgData['name'] ?? null;
                $orgDir3 = $orgData['identifier'] ?? null;
                $orgNif = $orgData['nif'] ?? null;

                if ($orgName || $orgDir3) {
                    $inCache = false;

                    if ($orgDir3 && isset($this->organizationsCache['dir3:' . $orgDir3])) {
                        $inCache = true;
                    }
                    if (!$inCache && $orgNif && isset($this->organizationsCache['nif:' . $orgNif])) {
                        $inCache = true;
                    }
                    if (!$inCache) {
                        $key = $this->makeOrganizationKey($orgDir3, $orgName);
                        if (isset($this->organizationsCache[$key])) {
                            $inCache = true;
                        }
                    }

                    if (!$inCache) {
                        $key = $this->makeOrganizationKey($orgDir3, $orgName);
                        // Deduplicate within batch by key
                        if (!isset($newOrganizations[$key])) {
                            $newOrganizations[$key] = [
                                'name' => $orgName,
                                'identifier' => $orgDir3,
                                'nif' => $orgNif,
                                'type_code' => $orgData['type_code'] ?? null,
                                'hierarchy' => isset($orgData['hierarchy']) ? json_encode($orgData['hierarchy']) : null,
                                'parent_name' => $orgData['parent_name'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            // Store address/contacts for post-insert persistence
                            $newOrgMeta[$key] = [
                                '_address' => $orgData['_address'] ?? null,
                                '_contacts' => $orgData['_contacts'] ?? null,
                                'identifier' => $orgDir3,
                                'name' => $orgName,
                            ];
                        }
                    }
                }

                // Collect unknown companies from _award
                $awardData = $data['_award'] ?? [];
                $companyName = $awardData['company_name'] ?? null;
                $companyNif = $awardData['company_nif'] ?? null;

                if ($companyName || $companyNif) {
                    $inCache = false;

                    if ($companyNif && isset($this->companiesCache['nif:' . $companyNif])) {
                        $inCache = true;
                    }
                    if (!$inCache) {
                        $key = $this->makeCompanyKey($companyNif, $companyName);
                        if (isset($this->companiesCache[$key])) {
                            $inCache = true;
                        }
                    }

                    if (!$inCache) {
                        $key = $this->makeCompanyKey($companyNif, $companyName);
                        if (!isset($newCompanies[$key])) {
                            $newCompanies[$key] = [
                                'name' => $companyName,
                                'identifier' => $companyNif,
                                'nif' => $companyNif,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }

            // Bulk insert new organizations (insertOrIgnore handles race conditions)
            if (!empty($newOrganizations)) {
                Organization::insertOrIgnore(array_values($newOrganizations));

                // Refresh cache: query back the newly inserted organizations
                $dir3Codes = array_filter(array_column(array_values($newOrganizations), 'identifier'));
                $nombres = array_filter(array_column(array_values($newOrganizations), 'name'));

                $query = Organization::select('id', 'identifier', 'name', 'nif');
                if (!empty($dir3Codes) && !empty($nombres)) {
                    $query->where(function ($q) use ($dir3Codes, $nombres) {
                        $q->whereIn('identifier', $dir3Codes)
                          ->orWhereIn('name', $nombres);
                    });
                } elseif (!empty($dir3Codes)) {
                    $query->whereIn('identifier', $dir3Codes);
                } else {
                    $query->whereIn('name', $nombres);
                }

                foreach ($query->get() as $org) {
                    if ($org->identifier) {
                        $this->organizationsCache['dir3:' . $org->identifier] = $org->id;
                    }
                    if ($org->nif) {
                        $this->organizationsCache['nif:' . $org->nif] = $org->id;
                    }
                    $key = $this->makeOrganizationKey($org->identifier, $org->name);
                    $this->organizationsCache[$key] = $org->id;
                }

                // Persist addresses and contacts for newly created organizations
                $this->persistOrganizationMeta($newOrgMeta);

                // Update Redis cache
                Cache::tags([self::CACHE_TAG])->put('organizations_cache', $this->organizationsCache, self::CACHE_TTL);
            }

            // Bulk insert new companies
            if (!empty($newCompanies)) {
                Company::insertOrIgnore(array_values($newCompanies));

                // Refresh cache
                $nifs = array_filter(array_column(array_values($newCompanies), 'nif'));
                $nombres = array_filter(array_column(array_values($newCompanies), 'name'));

                $query = Company::select('id', 'identifier', 'name', 'nif');
                if (!empty($nifs) && !empty($nombres)) {
                    $query->where(function ($q) use ($nifs, $nombres) {
                        $q->whereIn('nif', $nifs)
                          ->orWhereIn('name', $nombres);
                    });
                } elseif (!empty($nifs)) {
                    $query->whereIn('nif', $nifs);
                } else {
                    $query->whereIn('name', $nombres);
                }

                foreach ($query->get() as $company) {
                    if ($company->nif) {
                        $this->companiesCache['nif:' . $company->nif] = $company->id;
                    }
                    $key = $this->makeCompanyKey($company->nif, $company->name);
                    $this->companiesCache[$key] = $company->id;
                }

                // Update Redis cache
                Cache::tags([self::CACHE_TAG])->put('companies_cache', $this->companiesCache, self::CACHE_TTL);
            }

            // ── Pass 2: Build contract/award data arrays with resolved IDs ──

            $contractsData = [];
            $awardsData = [];
            $entriesWithRelations = []; // track entries that have notices/documents

            foreach ($entries as $data) {
                if (empty($data['external_id']) || empty($data['expediente'])) {
                    continue;
                }

                // Resolve Organization ID from cache
                $organizationId = $this->resolveOrganizationFromCache($data);

                // Resolve Company ID from cache
                $companyId = $this->resolveCompanyFromCache($data);

                $contractsData[] = [
                    'external_id' => $data['external_id'],
                    'expediente' => $data['expediente'],
                    'link' => $data['link'] ?? null,
                    'status_code' => $data['status_code'] ?? null,
                    'objeto' => $data['objeto'] ?? null,
                    'tipo_contrato_code' => $data['tipo_contrato_code'] ?? null,
                    'subtipo_contrato_code' => $data['subtipo_contrato_code'] ?? null,
                    'importe_sin_iva' => $data['importe_sin_iva'] ?? null,
                    'importe_con_iva' => $data['importe_con_iva'] ?? null,
                    'valor_estimado' => $data['valor_estimado'] ?? null,
                    'procedimiento_code' => $data['procedimiento_code'] ?? null,
                    'urgencia_code' => $data['urgencia_code'] ?? null,
                    'cpv_codes' => isset($data['cpv_codes']) ? json_encode($data['cpv_codes']) : null,
                    'comunidad_autonoma' => $data['comunidad_autonoma'] ?? null,
                    'nuts_code' => $data['nuts_code'] ?? null,
                    'lugar_ejecucion' => $data['lugar_ejecucion'] ?? null,
                    'fecha_presentacion_limite' => $data['fecha_presentacion_limite'] ?? null,
                    'duracion' => $data['duracion'] ?? null,
                    'duracion_unidad' => $data['duracion_unidad'] ?? null,
                    'fecha_inicio' => $data['fecha_inicio'] ?? null,
                    'fecha_fin' => $data['fecha_fin'] ?? null,
                    'submission_method_code' => $data['submission_method_code'] ?? null,
                    'contracting_system_code' => $data['contracting_system_code'] ?? null,
                    'fecha_disponibilidad_docs' => $data['fecha_disponibilidad_docs'] ?? null,
                    'hora_presentacion_limite' => $data['hora_presentacion_limite'] ?? null,
                    'criterios_adjudicacion' => isset($data['criterios_adjudicacion']) ? json_encode($data['criterios_adjudicacion']) : null,
                    'garantia_tipo_code' => $data['garantia_tipo_code'] ?? null,
                    'garantia_porcentaje' => $data['garantia_porcentaje'] ?? null,
                    'idioma' => $data['idioma'] ?? null,
                    'opciones_descripcion' => $data['opciones_descripcion'] ?? null,
                    'organization_id' => $organizationId,
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Prepare award if there's a winner
                $awardData = $data['_award'] ?? [];
                if ($companyId && !empty($awardData['company_name'])) {
                    $awardsData[$data['external_id']] = [
                        'company_id' => $companyId,
                        'amount' => $awardData['amount'] ?? null,
                        'amount_without_tax' => $awardData['amount_without_tax'] ?? null,
                        'procedure_type' => $awardData['procedure_type'] ?? null,
                        'urgency' => $awardData['urgency'] ?? null,
                        'award_date' => $awardData['award_date'] ?? null,
                        'start_date' => $data['fecha_inicio'] ?? null,
                        'formalization_date' => $awardData['formalization_date'] ?? null,
                        'contract_number' => $awardData['contract_number'] ?? null,
                        'sme_awarded' => $awardData['sme_awarded'] ?? null,
                        'num_offers' => $awardData['num_offers'] ?? null,
                        'result_code' => $awardData['result_code'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Track entries with notices or documents
                $notices = $data['_notices'] ?? [];
                $documents = $data['_documents'] ?? [];
                if (!empty($notices) || !empty($documents)) {
                    $entriesWithRelations[$data['external_id']] = [
                        '_notices' => $notices,
                        '_documents' => $documents,
                    ];
                }
            }

            // Batch upsert contracts
            if (!empty($contractsData)) {
                // Deduplicate by external_id (keep last occurrence)
                $contractsData = array_values(
                    collect($contractsData)->keyBy('external_id')->all()
                );

                Contract::upsert(
                    $contractsData,
                    ['external_id'],
                    [
                        'expediente', 'link', 'status_code', 'objeto',
                        'tipo_contrato_code', 'subtipo_contrato_code',
                        'importe_sin_iva', 'importe_con_iva', 'valor_estimado',
                        'procedimiento_code', 'urgencia_code',
                        'cpv_codes', 'comunidad_autonoma', 'nuts_code', 'lugar_ejecucion',
                        'fecha_presentacion_limite', 'duracion', 'duracion_unidad',
                        'fecha_inicio', 'fecha_fin',
                        'submission_method_code', 'contracting_system_code',
                        'fecha_disponibilidad_docs', 'hora_presentacion_limite',
                        'criterios_adjudicacion', 'garantia_tipo_code', 'garantia_porcentaje',
                        'idioma', 'opciones_descripcion',
                        'organization_id', 'synced_at', 'updated_at',
                    ]
                );
            }

            // Upsert awards (need contract_id FK first)
            if (!empty($awardsData)) {
                $contractIds = Contract::whereIn('external_id', array_keys($awardsData))
                    ->pluck('id', 'external_id')
                    ->toArray();

                $awardsToInsert = [];
                foreach ($awardsData as $externalId => $awardRow) {
                    if (isset($contractIds[$externalId])) {
                        $awardRow['contract_id'] = $contractIds[$externalId];
                        $awardsToInsert[] = $awardRow;
                    }
                }

                if (!empty($awardsToInsert)) {
                    Award::upsert(
                        $awardsToInsert,
                        ['contract_id', 'company_id'],
                        [
                            'amount', 'amount_without_tax', 'procedure_type', 'urgency',
                            'award_date', 'start_date', 'formalization_date',
                            'contract_number', 'sme_awarded', 'num_offers', 'result_code',
                            'updated_at',
                        ]
                    );
                }
            }

            // Sync notices and documents per contract
            if (!empty($entriesWithRelations)) {
                $contractIds = Contract::whereIn('external_id', array_keys($entriesWithRelations))
                    ->pluck('id', 'external_id')
                    ->toArray();

                foreach ($entriesWithRelations as $externalId => $relations) {
                    $contractId = $contractIds[$externalId] ?? null;
                    if (!$contractId) continue;

                    $notices = $relations['_notices'] ?? [];
                    $documents = $relations['_documents'] ?? [];

                    if (!empty($notices)) {
                        ContractNotice::where('contract_id', $contractId)->delete();
                        foreach ($notices as $notice) {
                            ContractNotice::create(array_merge($notice, ['contract_id' => $contractId]));
                        }
                    }

                    if (!empty($documents)) {
                        ContractDocument::where('contract_id', $contractId)->delete();
                        foreach ($documents as $doc) {
                            ContractDocument::create(array_merge($doc, ['contract_id' => $contractId]));
                        }
                    }
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Polymorphic persistence for new organizations (addresses + contacts)
    // ─────────────────────────────────────────────────────────────────────

    private function persistOrganizationMeta(array $orgMeta): void
    {
        $morphType = 'Modules\\Contracts\\Models\\Organization';

        foreach ($orgMeta as $meta) {
            $identifier = $meta['identifier'] ?? null;
            $name = $meta['name'] ?? null;

            // Resolve the org ID from cache
            $orgId = null;
            if ($identifier && isset($this->organizationsCache['dir3:' . $identifier])) {
                $orgId = $this->organizationsCache['dir3:' . $identifier];
            }
            if (!$orgId) {
                $key = $this->makeOrganizationKey($identifier, $name);
                $orgId = $this->organizationsCache[$key] ?? null;
            }

            if (!$orgId) continue;

            // Persist address
            $addressData = $meta['_address'] ?? null;
            if ($addressData) {
                Address::updateOrCreate(
                    [
                        'addressable_type' => $morphType,
                        'addressable_id' => $orgId,
                    ],
                    [
                        'line' => $addressData['line'] ?? null,
                        'postal_code' => $addressData['postal_code'] ?? null,
                        // city_name/country_code from parser — stored as-is; geo resolution deferred
                    ]
                );
            }

            // Persist contacts
            $contacts = $meta['_contacts'] ?? null;
            if ($contacts) {
                // Delete old contacts for this org, then re-insert
                Contact::where('contactable_type', $morphType)
                    ->where('contactable_id', $orgId)
                    ->delete();

                foreach ($contacts as $contact) {
                    Contact::create([
                        'contactable_type' => $morphType,
                        'contactable_id' => $orgId,
                        'type' => $contact['type'],
                        'value' => $contact['value'],
                    ]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cache resolution helpers (Pass 2 — all entities already bulk-inserted)
    // ─────────────────────────────────────────────────────────────────────

    private function resolveOrganizationFromCache(array $data): ?int
    {
        $orgData = $data['_organization'] ?? [];
        $name = $orgData['name'] ?? null;
        $dir3 = $orgData['identifier'] ?? null;
        $nif = $orgData['nif'] ?? null;

        if (!$name && !$dir3) return null;

        if ($dir3 && isset($this->organizationsCache['dir3:' . $dir3])) {
            return $this->organizationsCache['dir3:' . $dir3];
        }

        if ($nif && isset($this->organizationsCache['nif:' . $nif])) {
            return $this->organizationsCache['nif:' . $nif];
        }

        $key = $this->makeOrganizationKey($dir3, $name);
        return $this->organizationsCache[$key] ?? null;
    }

    private function resolveCompanyFromCache(array $data): ?int
    {
        $awardData = $data['_award'] ?? [];
        $name = $awardData['company_name'] ?? null;
        $nif = $awardData['company_nif'] ?? null;

        if (!$name && !$nif) return null;

        if ($nif && isset($this->companiesCache['nif:' . $nif])) {
            return $this->companiesCache['nif:' . $nif];
        }

        $key = $this->makeCompanyKey($nif, $name);
        return $this->companiesCache[$key] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cache key generators
    // ─────────────────────────────────────────────────────────────────────

    private function makeOrganizationKey(?string $identifier, ?string $name): string
    {
        if (empty($identifier) && empty($name)) {
            return Str::uuid()->toString();
        }
        return md5(($identifier ?? '') . '|' . ($name ?? ''));
    }

    private function makeCompanyKey(?string $nif, ?string $name = null): string
    {
        if (!empty($nif)) {
            return md5($nif);
        }
        if (!empty($name)) {
            return md5('name:' . $name);
        }
        return Str::uuid()->toString();
    }
}
