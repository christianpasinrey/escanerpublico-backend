# Contracts Module — Backend v2 · Design Spec

**Date:** 2026-04-24
**Author:** Brainstorming session con Christian
**Status:** Draft pendiente de revisión

## Contexto

El módulo `Contracts` es el primero y más maduro de la plataforma Escáner Público. La ingesta actual del feed PLACSP (Plataforma de Contratación del Sector Público) captura solo un subconjunto de los datos disponibles en el XML CODICE, y el modelo de datos no soporta multi-lote, modificaciones, snapshots históricos, ni dirección del adjudicatario. Las fichas públicas planeadas (contrato, empresa, organismo) requieren un backend más rico.

Este spec define una refactorización completa del backend del módulo: parser decompuesto en extractors, ingestor idempotente con captura de snapshots, comando de reproceso resumible, API pública con query builder (`spatie/laravel-query-builder`), cache en tres capas (Cloudflare edge + Redis + DB), landing pública con documentación interactiva (`dedoc/scramble` + `scalar/laravel`), y una barra de tests Premium (unit + feature + benchmarks + mutation testing + coverage ≥ 85%).

Este es el **Spec 1 de un plan de 3-4**. Los siguientes specs abordarán:
- **Spec 2**: Ficha de contrato (frontend Vue).
- **Spec 3**: Ficha de empresa (frontend Vue, página nueva).
- **Spec 4 (futuro)**: Snapshot history exploitation — queries de diff, detección de anomalías, panel de auditoría.

## Decisiones de alcance acordadas

| Pregunta | Decisión |
|---|---|
| Decomposición | Por capas: backend primero (este spec), luego frontend |
| Migración de datos | Wipe + reproceso total desde `.atom` locales (no hay producción) |
| Ambición del modelo | Nivel 3 "arqueológico" escalado: snapshot history scaffolded en Spec 1, diff/fraude en Spec 4 |
| Forma de la API | Query builder con `spatie/laravel-query-builder` (include, fields, filter, sort) |
| Cache | Cloudflare edge (SWR) + Redis tagged + DB. Invalidación: CF purge-by-URL + Redis tag flush al re-ingest |
| Calidad / tests | Premium: fixtures reales, unit por extractor, feature tests, benchmarks Pest, Infection MSI ≥ 70%, coverage ≥ 85%, CI bloqueante |
| Multi-lote | Tabla dedicada `contract_lots`; `awards` apunta a `contract_lot_id` |
| Arquitectura | Service-oriented Laravel + XMLReader streaming dentro del parser |
| API docs | Pública sin auth + landing `/` + `/docs` interactivas (Scramble + Scalar) |
| Metodología | Un worktree por fase + subagents especializados paralelos |

## Hallazgos del análisis previo

### Naturaleza del feed PLACSP

Analizado con el contrato real `CG-09/17` (id 1892425) cruzado por 201801 y 201803 ATOMs locales:

- Un contrato se **re-publica N veces** en distintos snapshots mensuales a medida que avanza de fase.
- Cada snapshot incluye la lista acumulada de `ValidNoticeInfo`, cada notice con su propio `IssueDate`.
- Los códigos `DOC_*` de los notices SON la línea temporal: `DOC_PREV`/`DOC_CD`/`DOC_CN` (convocatoria) → `DOC_CAN_ADJ` (adjudicación) → `DOC_FORM` (formalización) → `DOC_MOD`/`DOC_PRI` (modificaciones/prórrogas) → `DOC_ANUL`/`DOC_DES`/`DOC_REN` (salidas).
- `<entry>.updated` es el timestamp del snapshot — clave para idempotencia.
- `<at:deleted-entry>` al inicio del feed son contratos anulados; se ignoran actualmente.
- Multi-lote real existe en el XML vía `ProcurementProjectLot` + múltiples `TenderResult`.

### Datos disponibles en el XML que el parser actual NO captura

- Organización: `BuyerProfileURIID`, `ActivityCode`, `ID_PLATAFORMA`.
- Proyecto: `MixContractIndicator`.
- TenderResult: `Description` narrativa, `LowerTenderAmount`/`HigherTenderAmount`, `SMEsReceivedTenderQuantity`, `StartDate` del result, `WinningParty/PhysicalLocation` (dirección del adjudicatario — crítica para ficha empresa).
- TenderingTerms: `FundingProgramCode`, `OverThresholdIndicator`, `ProcurementNationalLegislationCode`, `ReceivedAppealQuantity`, `VariantConstraintIndicator`, `RequiredCurriculaIndicator`.
- Criterios de adjudicación: `AwardingCriteriaTypeCode` (OBJ/SUBJ), `AwardingCriteriaSubTypeCode`, `Note` (fórmula completa).

### Ficheros locales

88 meses completos extraídos en `storage/app/placsp/YYYYMM/extracted/*.atom` (201801 → 202603). No hace falta descargar para el reproceso total.

## Arquitectura

Flujo top-level:

```
CLI (SyncContracts | ReprocessContracts)
    ↓ dispatch
Queue (Redis) — ProcessPlacspFile Job
    ↓ stream
PlacspStreamParser (XMLReader) → PlacspEntryParser (compone extractors)
    ↓ EntryDTO | TombstoneDTO
ContractIngestor (transaccional, idempotente, snapshots)
    ↓ SQL
MySQL (12 tablas: 5 modificadas + 7 nuevas)
    ↑ read
Controllers + ContractsQueryBuilder (spatie) + API Resources
    ↓ Cache-Control + Redis tag
Cache (Cloudflare edge SWR → Redis tagged → DB)
```

### Principios

- **Idempotencia total.** Re-procesar el mismo atom dos veces = 0 diff en BD. Test de regresión en cada PR.
- **Streaming.** El parser nunca carga el atom completo en memoria (`SimpleXMLElement` cuesta ~60 MB para un atom de 9 MB). `XMLReader` itera entries con memoria constante ~5-10 MB.
- **Graceful degradation.** Si un extractor individual falla, se loggea a `parse_errors` con el fragmento XML pero el resto del entry sigue siendo ingestado.
- **Cache-first.** Cualquier lectura pasa por Cloudflare → Redis → DB. Invalidación explícita post-ingest por tags.
- **Query builder whitelist.** Toda inclusión/filtro/sort es explícita en el controlador. Máximo 3 niveles de nesting. `per_page` ≤ 100.
- **Verificar local antes de descargar.** `SyncContracts` comprueba presencia de atoms extraídos antes de descargar ZIP. `--force-download` para forzar.

## Modelo de datos

### Tablas modificadas

**`contracts`** — añade:
- `buyer_profile_uri` varchar(500) nullable
- `activity_code` varchar(10) nullable
- `mix_contract_indicator` boolean nullable
- `funding_program_code` varchar(20) nullable
- `over_threshold_indicator` boolean nullable
- `national_legislation_code` varchar(20) nullable
- `received_appeal_quantity` int unsigned nullable
- `snapshot_updated_at` timestamp nullable — pivote de idempotencia
- `annulled_at` timestamp nullable — fecha de tombstone

Mantiene los campos de budget/CPV/duración como resumen a nivel proyecto (redundantes con el "lote 1" en single-lot contracts; consistente con el XML que los publica en ambos sitios).

Índices: `(status_code, snapshot_updated_at DESC)`, `(organization_id, status_code)`, `(tipo_contrato_code, status_code)`, `(annulled_at)` para WHERE IS NULL. `FULLTEXT(objeto, expediente)` para búsqueda.

**`organizations`** — añade:
- `buyer_profile_uri` varchar(500) nullable
- `activity_code` varchar(10) nullable
- `platform_id` varchar(50) nullable

**`awards`** — cambia FK + amplía:
- `contract_id` → `contract_lot_id` (rename + nuevo FK target)
- Añade: `description` text nullable, `start_date` date nullable, `lower_tender_amount` decimal(15,2) nullable, `higher_tender_amount` decimal(15,2) nullable, `smes_received_tender_quantity` int unsigned nullable
- Unique: `(contract_lot_id, company_id)`
- Índices: `(company_id, award_date DESC)`, `(contract_lot_id)`

**`contract_notices`** — amplía constraint:
- Unique: `(contract_id, notice_type_code, issue_date)` — reemplaza delete+recreate con upsert idempotente
- Opcional: `first_seen_in_snapshot_id` FK nullable
- Índice: `(contract_id, issue_date)`

**`contract_documents`** — amplía constraint:
- Unique: `(contract_id, uri)`

### Tablas nuevas

**`contract_lots`**
- `id` bigserial
- `contract_id` FK
- `lot_number` int unsigned — 1..N
- `title` varchar(500) nullable
- `description` text nullable
- `tipo_contrato_code` varchar(10) nullable
- `subtipo_contrato_code` varchar(10) nullable
- `cpv_codes` json
- `budget_with_tax` decimal(15,2) nullable
- `budget_without_tax` decimal(15,2) nullable
- `estimated_value` decimal(15,2) nullable
- `duration` decimal(8,2) nullable
- `duration_unit` varchar(10) nullable
- `start_date` date nullable
- `end_date` date nullable
- `nuts_code` varchar(10) nullable
- `lugar_ejecucion` varchar(255) nullable
- `options_description` text nullable
- `timestamps`
- Unique: `(contract_id, lot_number)`
- Índice: `(contract_id)`

Migración: por cada contrato existente, crear un lote sintético con `lot_number = 1` conteniendo los campos de budget/CPV/duración actuales.

**`awarding_criteria`**
- `id`, `contract_lot_id` FK, `type_code` varchar(5) — OBJ/SUBJ, `subtype_code` varchar(10) nullable, `description` text, `note` text nullable, `weight_numeric` decimal(8,2) nullable, `sort_order` int unsigned, `timestamps`
- Unique: `(contract_lot_id, sort_order)`

**`contract_modifications`**
- `id`, `contract_id` FK, `type` enum('modification','extension','cancellation','assignment','annulment'), `issue_date` date, `effective_date` date nullable, `description` text nullable, `amount_delta` decimal(15,2) nullable, `new_end_date` date nullable, `related_notice_id` FK nullable, `timestamps`
- Unique: `(contract_id, type, issue_date)`

**`contract_snapshots`** ⭐ base nivel 3
- `id`, `contract_id` FK, `entry_updated_at` timestamp, `status_code` varchar(5), `content_hash` char(40) — SHA-1 del payload canonical, `payload` json — DTO parseado serializado, `source_atom` varchar(500) nullable, `ingested_at` timestamp, `timestamps`
- Unique: `(contract_id, entry_updated_at)`
- Índices: `(contract_id, entry_updated_at DESC)`, `(content_hash)`

Estimación: 88 meses × ~500 entries × ~3-5 snapshots por contrato ≈ **~700k filas, ~500 MB** con payload JSON incluido.

**`reprocess_runs`**
- `id`, `name` varchar(100) nullable, `status` enum('pending','running','completed','failed','cancelled'), `started_at` timestamp nullable, `finished_at` timestamp nullable, `total_atoms` int nullable, `processed_atoms` int default 0, `total_entries` int default 0, `failed_entries` int default 0, `config` json, `timestamps`

**`reprocess_atom_runs`**
- `id`, `reprocess_run_id` FK, `atom_path` varchar(500), `atom_hash` char(40), `status` enum('pending','running','completed','failed'), `started_at` timestamp nullable, `finished_at` timestamp nullable, `entries_processed` int default 0, `entries_failed` int default 0, `error_message` text nullable, `timestamps`
- Índices: `(reprocess_run_id, status)`

**`parse_errors`**
- `id`, `reprocess_atom_run_id` FK nullable, `atom_path` varchar(500), `entry_external_id` varchar(500) nullable, `error_code` varchar(50), `error_message` text, `raw_fragment` text nullable, `timestamps`
- Índices: `(error_code)`, `(reprocess_atom_run_id)`

## Parser

### Flujo

```
ProcessPlacspFile::handle($atomPath)
  → PlacspStreamParser::stream($atomPath)
      → per <at:deleted-entry>: emit TombstoneDTO → Ingestor::handleTombstone()
      → per <entry>: XMLReader::expand() → simplexml_import_dom()
         → PlacspEntryParser::parse($xml) → EntryDTO
      → batch de 500 entries → Ingestor::ingestBatch($entries)
```

### Extractors (10)

Cada extractor recibe un `SimpleXMLElement` del nodo correspondiente y devuelve un DTO tipado (readonly classes PHP 8.4). Errores individuales → `parse_errors`, resto del entry sigue.

| Extractor | Input XML | Output DTO |
|---|---|---|
| `TombstoneExtractor` | `<at:deleted-entry>` | `TombstoneDTO { ref, when }` |
| `OrganizationExtractor` | `cac-place-ext:LocatedContractingParty` | `OrganizationDTO { name, dir3, nif, platform_id, buyer_profile_uri, activity_code, type_code, hierarchy[], address, contacts[] }` |
| `ProjectExtractor` | `cac:ProcurementProject` top-level | `ProjectDTO { objeto, tipo, subtipo, budget, cpv_codes[], location, period }` — usado si no hay lotes |
| `LotsExtractor` | `cac:ProcurementProjectLot[]` | `LotDTO[]`; si no hay lotes devuelve `[LotDTO]` con ProjectDTO adaptado a `lot_number=1` |
| `ProcessExtractor` | `cac:TenderingProcess` | `ProcessDTO { procedure_code, urgency_code, submission_method, contracting_system, deadlines, document_availability }` |
| `ResultsExtractor` | `cac:TenderResult[]` | `ResultDTO[]` multi-lote con `WinningPartyDTO { name, nif, address }` |
| `TermsExtractor` | `cac:TenderingTerms` | `TermsDTO { language, guarantee, funding_program, national_legislation, over_threshold, appeals, variant, curricula }` |
| `CriteriaExtractor` | `cac:TenderingTerms/AwardingTerms/AwardingCriteria[]` | `CriterionDTO[] { type_code, subtype_code, description, note, weight }` |
| `NoticesExtractor` | `cac-place-ext:ValidNoticeInfo[]` | `NoticeDTO[] { notice_type_code, publication_media, issue_date, document_uri, filename, document_type_code }` |
| `DocumentsExtractor` | `cac:LegalDocumentReference[] + TechnicalDocumentReference[] + AdditionalDocumentReference[] + cac-place-ext:GeneralDocument[]` | `DocumentDTO[] { type, name, uri, hash }` |

### EntryDTO compuesto

```php
final readonly class EntryDTO {
  public function __construct(
    public string $external_id,
    public ?string $link,
    public string $expediente,
    public string $status_code,
    public \DateTimeImmutable $entry_updated_at,
    public OrganizationDTO $organization,
    /** @var LotDTO[] */ public array $lots,
    public ProcessDTO $process,
    /** @var ResultDTO[] */ public array $results,
    public TermsDTO $terms,
    /** @var array<int, CriterionDTO[]> por lot_number */ public array $criteria_by_lot,
    /** @var NoticeDTO[] */ public array $notices,
    /** @var DocumentDTO[] */ public array $documents,
  ) {}
}
```

### XMLReader streaming

```php
$reader = new \XMLReader();
$reader->open($atomPath);
while ($reader->read()) {
    if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'entry') {
        $dom = $reader->expand();
        $xml = simplexml_import_dom($dom);
        $entry = $this->entryParser->parse($xml);
        yield $entry;
        // $dom descartado automáticamente tras unset()
    }
}
```

## Ingestor

### API pública

```php
class ContractIngestor {
  public function handleTombstone(TombstoneDTO $t): void;
  public function ingestBatch(array $entries): BatchResult; // EntryDTO[]
}
```

### Flujo `ingestBatch` (transaccional por batch de 500)

1. **Pre-filtro idempotencia**: calcular `content_hash` del EntryDTO canónico (JSON ordenado). Hash duplicado vs último snapshot → skip completo.
2. **Pre-filtro recencia**: `SELECT external_id, snapshot_updated_at FROM contracts WHERE external_id IN (...)`. Si `entry.updated <= snapshot_updated_at` → skip.
3. **Pass 1 — Bulk-resolve**:
   - Cache en memoria `organizationsCache[dir3:X / nif:Y / name_hash:Z]`. Pre-cargada desde Redis tag `placsp_import`.
   - Coleccionar desconocidas, `Organization::insertOrIgnore($new)` + `Company::insertOrIgnore($new)`.
   - Refresh cache con IDs nuevos.
   - Persistir `Address` polimórfica de organization + companies adjudicatarias (novedad).
4. **Pass 2 — Upserts**:
   - `Contract::upsert($rows, ['external_id'], [...columns, 'snapshot_updated_at'])`.
   - `ContractLot::upsert($rows, ['contract_id', 'lot_number'], [...])`.
   - `Award::upsert($rows, ['contract_lot_id', 'company_id'], [...])`.
   - `AwardingCriterion::upsert($rows, ['contract_lot_id', 'sort_order'], [...])`.
   - `ContractNotice::upsert($rows, ['contract_id', 'notice_type_code', 'issue_date'], [...])` — **idempotente**.
   - `ContractDocument::upsert($rows, ['contract_id', 'uri'], [...])`.
   - `ContractModification::upsert($rows, ['contract_id', 'type', 'issue_date'], [...])`.
5. **Snapshot capture**: `ContractSnapshot::insertOrIgnore($rows)` — unique `(contract_id, entry_updated_at)`.
6. **Actualizar snapshot_updated_at** si más reciente.
7. **Cache invalidation post-commit**:
   - Redis: `Cache::tags(['contract:'.$id, 'org:'.$orgId, 'company:'.$companyId])->flush()`.
   - Cloudflare: dispatch `PurgeContractUrls($contractIds)`.

### Tombstone

```php
Contract::where('external_id', $t->ref)->update([
    'status_code' => 'ANUL',
    'annulled_at' => $t->when,
]);
// + invalidation cache + snapshot tipo tombstone
```

### Resolución de entidades — keys

Organization (prioridad descendente):
1. `dir3:{code}` (gold standard)
2. `nif:{nif}`
3. `name_hash:{sha1(normalize(name))}` — fallback

Company:
1. `nif:{nif}` (gold standard)
2. `name_hash:{sha1(normalize(name))}`

`normalize(name)`: lowercase + eliminar puntuación + colapsar espacios + sin acentos. Previene "S.L." vs "SL" duplicates.

### Errores

- Excepción en un entry → `ParseError::create([...])` con fragmento XML + error_code + message.
- Batch sigue con resto de entries.
- Batch falla entero (BD) → retry con `$tries = 3`.

## Comando de reproceso

### `php artisan contracts:reprocess`

**Opciones:**
- `--run-id=N` — reanuda un run existente (skip completados).
- `--resume` — reanuda último run `failed`/`running`.
- `--from=YYYYMM --to=YYYYMM` — rango de meses (default: todos).
- `--atoms=path1,path2` — lista explícita (modo surgical).
- `--parallel=N` — concurrent jobs (default 4).
- `--sync` — inline sin cola (debugging).
- `--dry-run` — enumera + crea run record, no dispatcha.
- `--fresh` — ⚠ confirma y ejecuta `migrate:fresh` (solo reproceso inicial Spec 1).

**Flujo:**

1. Enumerar atoms de `storage/app/placsp/{month}/extracted/*.atom`. Warning sin descarga automática si falta algún mes.
2. Crear/reanudar `ReprocessRun` + filas `ReprocessAtomRun` (status pending, atom_hash).
3. Dispatch con throttling — Horizon queue dedicada `contracts-reprocess` con N workers.
4. `ProcessPlacspFile` actualiza `ReprocessAtomRun` (running → completed/failed).
5. Observabilidad: progress bar CLI (consulta cada 2s), endpoint interno `GET /api/internal/reprocess-runs/{id}` (IP whitelist), log JSON-lines por atom.
6. Post-run: `Cache::tags(['placsp_import'])->flush()` + Cloudflare purge global.

**Estimación:** 500k entries locales / (4 workers × 300 entries/s) ≈ **~7 min** escenario realista. Validado por benchmark.

## API pública

### Endpoints

```
GET /api/v1/contracts                    listado paginado
GET /api/v1/contracts/{external_id}      ficha completa
GET /api/v1/contracts/{id}/snapshots     histórico (expuesto para Spec 4)

GET /api/v1/organizations
GET /api/v1/organizations/{id}
GET /api/v1/organizations/{id}/stats

GET /api/v1/companies
GET /api/v1/companies/{id}
GET /api/v1/companies/{id}/stats

GET /api/v1/lots                         útil para cross-search
```

Routing por `external_id` (acepta ID numérico PLACSP o URL completa). URLs estables aunque haya `migrate:fresh`.

### Query builder — whitelist explícita

`ContractsQueryBuilder` extiende `Spatie\QueryBuilder\QueryBuilder` con:

- **`allowedFilters`**: `status_code`, `tipo_contrato_code`, `organization_id`, `nuts_code`, `funding_program_code`, `over_threshold_indicator`, scope `awarded_to_company`, scope `amount_between`, scope `awarded_between`, scope `criterio_tipo` (OBJ/SUBJ/MIXED), scope `search` (FULLTEXT).
- **`allowedIncludes`**: `organization`, `organization.addresses`, `organization.contacts`, `lots`, `lots.awards`, `lots.awards.company`, `lots.awards.company.addresses`, `lots.criteria`, `notices`, `modifications`, `documents`, `timeline` (virtual: notices + modifications ordenadas), `snapshots_summary` (slim: sin payload).
- **`allowedFields`**: sparse fieldsets estilo JSON:API.
- **`allowedSorts`**: `snapshot_updated_at`, `importe_con_iva`, `fecha_inicio`, `relevance` (custom `RelevanceSort`). Default: `-snapshot_updated_at`.

### Seguridad

- Todos los includes pasan por `whenLoaded()` en la Resource.
- Middleware `LimitNestedIncludes` — máximo 3 niveles de nesting.
- `per_page` 1-100, default 25.
- Search: `MATCH AGAINST` contra `FULLTEXT(objeto, expediente)` en lugar de `LIKE %`.

### API Resources

`ContractResource`, `LotResource`, `AwardResource`, `OrganizationResource`, `CompanyResource`, `TimelineEventResource` (virtual: mergea notices + modifications + status transitions con `{type, date, title, description, metadata}`), `ContractSnapshotSummaryResource` (slim), `ContractSnapshotFullResource` (con payload).

### Cache

| Endpoint | `Cache-Control` | Invalidación |
|---|---|---|
| `/contracts` listado | `public, s-maxage=60, stale-while-revalidate=300` | Tag `contracts:list` al final del run |
| `/contracts/{id}` | `public, s-maxage=3600, stale-while-revalidate=86400` | CF purge-by-URL + Redis tag `contract:{id}` |
| `/organizations/{id}` | `public, s-maxage=3600, stale-while-revalidate=86400` | Tag `org:{id}` |
| `/companies/{id}` | `public, s-maxage=3600, stale-while-revalidate=86400` | Tag `company:{id}` |
| `/stats` endpoints | `public, s-maxage=900, stale-while-revalidate=3600` | Tag por entidad |

**Cloudflare purge implementation:** package `sebdesign/laravel-cloudflare-zones`. Env `CLOUDFLARE_API_TOKEN` + `CLOUDFLARE_ZONE_ID`. Job `PurgeContractUrls` en cola baja prioridad — agrupa en batch de 30 URLs (límite API), retry exponencial.

### JSON envelope

```json
{
  "data": { /* Resource */ },
  "meta": { "snapshot_updated_at": "2018-03-21T16:47:49+01:00", "snapshots_count": 3 },
  "links": { "self": "...", "organization": "..." }
}
```

## Landing pública + docs interactivas

### Rutas

```
GET  /             LandingController@show   (Blade)
GET  /docs         Scalar UI
GET  /openapi.json Scramble route
GET  /health       { "status": "ok", "snapshot_updated_at": "...", "contracts": 812341 }
```

### Landing en `/`

- Hero con fuente de datos (PLACSP), última snapshot procesada (tiempo real desde Redis cached), N contratos/órganos/empresas.
- 3 snippets `curl` copiables (listado adjudicados, ficha con timeline, top empresas).
- Bloques reales: "Top 10 órganos por importe", "Últimas adjudicaciones" (consultas cacheadas 1h).
- Enlaces: `/docs`, `/openapi.json`, GitHub, contacto.
- Rate limits: `60 req/min` IP · `600 req/min` con `X-Api-Key` (futuro).
- Atribución PLACSP.
- CTA "¿Construyes algo con esto?".
- Blade + Tailwind (no Vue — SEO-friendly). Lighthouse ≥ 95.
- `Cache-Control: public, s-maxage=300, stale-while-revalidate=3600`.
- Stats refresh por scheduler: `php artisan landing:refresh-stats` cada 5 min → Redis key `landing:stats`.

### Docs interactivas `/docs`

- Generación OpenAPI 3.1: `dedoc/scramble` (extrae de type hints, Resources, FormRequests).
- UI: `scalar/laravel` en `/docs`.
- Raw spec: `/openapi.json`.
- Enriquecer con atributos PHP 8: `#[Group('Contracts')]`, `#[Summary('...')]`, `#[Parameter]`.

## Test strategy (Premium)

### Layout

```
tests/
├── Unit/Contracts/Extractors/          10 tests (uno por extractor)
├── Unit/Contracts/PlacspEntryParserTest
├── Unit/Contracts/PlacspStreamParserTest
├── Feature/Contracts/Ingestion/        Ingestor, Idempotency, SnapshotCapture, Tombstone, CacheInvalidation
├── Feature/Contracts/Reprocess/        Command, Resume
├── Feature/Contracts/Api/              ContractIndex, ContractShow, DisallowedIncludes, Organization, Company, CacheHeaders, JsonApiPagination
├── Feature/Contracts/Landing/          LandingPage, OpenApiDoc (valida con cebe/php-openapi), ScalarDocs
├── Benchmarks/                         ParserBenchmark, IngestorBenchmark, ApiEndpointBenchmark
└── Fixtures/placsp/                    11 fixtures XML (sample-01 a sample-10 + full-20-entries.atom del 201801 real recortado)
```

### Fixtures

- `sample-01-pub.xml` — entry única status PUB
- `sample-02-adj.xml` — ADJ
- `sample-03-res-formalized.xml` — RES con `Contract.IssueDate`
- `sample-04-multi-lote.xml` — 3 lotes, 2 con award
- `sample-05-with-modifications.xml`
- `sample-06-tombstone.xml` — solo `<at:deleted-entry>`
- `sample-07-sin-winner.xml` — ADJ pero WinningParty ausente
- `sample-08-malformed.xml` — XML inválido controlado (dispara `parse_errors`)
- `sample-09-with-criteria.xml` — 5 criterios OBJ + SUBJ
- `sample-10-with-modifications-and-extension.xml`
- `full-20-entries.atom` — recortado del `201801` real con 20 entries variadas

### Benchmarks Pest (`pest-plugin-benchmarks`)

```php
it('parses 1000 entries in under 500ms')->benchmark(...)->expect()->toBeLowerThan(500, 'ms');
it('ingests 500 entries with BD hit in under 3s')->benchmark(...)->expect()->toBeLowerThan(3000, 'ms');
it('GET /contracts/:id all includes warm cache under 300ms')->benchmark(...)->expect()->toBeLowerThan(300, 'ms');
it('GET /contracts/:id cold cache under 700ms')->beforeEach(flushCache)->benchmark(...)->expect()->toBeLowerThan(700, 'ms');
```

### Mutation testing

- Infection configurado sobre `app/Modules/Contracts/Services/Parser/*` + `Services/ContractIngestor`.
- MSI ≥ 70%.
- `@infection-ignore` en getters/setters triviales.
- Workflow `infection.yml` semanal + manual — no en cada PR (lento).
- Si baja de 70% abre issue automáticamente.

### Coverage

- PHPUnit + Xdebug.
- Target **≥ 85%** en el módulo Contracts (no en todo el proyecto).
- `phpunit.xml`: `<coverage pathCovered="app/Modules/Contracts" minimum="85"/>`.

### Idempotency regression test

```php
it('re-ingests same atom without diff in DB', function () {
    Artisan::call('contracts:reprocess', ['--atoms' => [fixture('full-20-entries.atom')], '--sync' => true]);
    $snapshot1 = DatabaseSnapshot::capture();

    Artisan::call('contracts:reprocess', ['--atoms' => [fixture('full-20-entries.atom')], '--sync' => true]);
    $snapshot2 = DatabaseSnapshot::capture();

    expect($snapshot1->diff($snapshot2))->toBeEmpty();
});
```

`DatabaseSnapshot::capture()` = helper que hace `SELECT * ORDER BY id` de todas las tablas del módulo y devuelve hash.

### Snapshot growth test

```php
it('creates one contract_snapshot per unique entry.updated', function () {
    processAtoms(['atom-pub.xml', 'atom-adj.xml', 'atom-res.xml']);

    $snapshots = ContractSnapshot::where('contract_id', $contractId)->orderBy('entry_updated_at')->get();
    expect($snapshots)->toHaveCount(3);
    expect($snapshots->pluck('status_code'))->toEqual(['PUB', 'ADJ', 'RES']);
});
```

### CI pipeline

```yaml
.github/workflows/tests.yml:
  - phpunit_unit               (fast, <30s)
  - phpunit_feature            (MySQL service, <3min)
  - benchmarks                 (<2min, falla si threshold exceeded)
  - coverage_gate              (<1min, falla si <85%)
  - lint                       (pint + phpstan L8 en app/Modules/Contracts)

.github/workflows/infection.yml (weekly):
  - infection_mutation_test    (40-60min, scheduled + workflow_dispatch)
```

### Políticas de merge

- PR no mergeable si CI falla.
- PR bloqueado si coverage baja respecto a main.
- PR bloqueado si benchmark supera threshold.
- PR requiere ≥ 1 test nuevo si añade extractor/endpoint/scope.

## Estructura de ficheros

```
app/Modules/Contracts/
├── Console/                    SyncContracts (modificado), ReprocessContracts (nuevo)
├── Http/
│   ├── Controllers/            Contract, Organization, Company, Lot, Landing, Health
│   ├── Middleware/             LimitNestedIncludes
│   ├── Resources/              ContractResource, LotResource, AwardResource,
│   │                           OrganizationResource, CompanyResource,
│   │                           TimelineEventResource,
│   │                           ContractSnapshotSummaryResource, ContractSnapshotFullResource
│   └── Sorts/                  RelevanceSort
├── Jobs/                       ProcessPlacspFile (refactor), PurgeContractUrls (nuevo)
├── Models/                     Contract, ContractLot, ContractNotice, ContractDocument,
│                               ContractModification, ContractSnapshot, AwardingCriterion,
│                               Award, Organization, Company,
│                               ReprocessRun, ReprocessAtomRun, ParseError
├── Services/
│   ├── ContractIngestor.php
│   ├── EntityResolver.php
│   ├── Parser/
│   │   ├── PlacspStreamParser.php
│   │   ├── PlacspEntryParser.php
│   │   ├── Extractors/         10 extractors
│   │   └── DTOs/               11 readonly DTOs
│   ├── QueryBuilder/           ContractsQueryBuilder, OrganizationsQB, CompaniesQB
│   ├── Cache/                  ContractCacheInvalidator, CloudflarePurger
│   └── Stats/                  LandingStatsService, OrganizationStatsService
├── Routes/                     api.php (amplía), web.php (nuevo)
├── Events/                     ContractSnapshotCreated, ReprocessRunCompleted
├── Database/
│   ├── Migrations/             12 migraciones
│   └── Factories/              para tests
└── ContractsServiceProvider.php

resources/views/landing/index.blade.php
config/cloudflare.php
tests/                          (ver Test strategy)
```

## Plan de fases (worktrees + subagents)

| # | Worktree | Branch | Depende de | Subagents | Duración |
|---|---|---|---|---|---|
| **1.0** | `wt-1.0-foundation` | `feature/contracts-v2-foundation` | `main` | Backend Architect | 1 día |
| **1.1** | `wt-1.1-parser` | `feature/contracts-v2-parser` | 1.0 | Backend Architect (stream+compositor) + 5 Backend Architect paralelos (2 tandas × 5 extractors) + Evidence Collector (fixtures) | 2 días |
| **1.2** | `wt-1.2-ingestor` | `feature/contracts-v2-ingestor` | 1.1 | Backend Architect + DevOps Automator (Horizon) | 2-3 días |
| **1.3** | `wt-1.3-api` | `feature/contracts-v2-api` | 1.0 (paralelo a 1.1/1.2) | Backend Architect + API Tester | 2 días |
| **1.4** | `wt-1.4-landing` | `feature/contracts-v2-landing` | 1.0 | Frontend Developer + Technical Writer | 1-2 días |
| **1.5** | `wt-1.5-integration` | `feature/contracts-v2-integration` | 1.1+1.2+1.3+1.4 | Performance Benchmarker + Reality Checker | 1 día |

### Gates por fase

- **1.0**: `migrate:fresh` limpio, PHPStan L8 verde, factories funcionan.
- **1.1**: extractors tests verdes, fixtures parsean a DTOs válidos, memoria <20 MB en atom de 20 MB.
- **1.2**: idempotency test verde, snapshot test verde, `reprocess --sync` en atom local sin errores.
- **1.3**: feature tests de todos los endpoints verdes, include matrix validada, benchmarks bajo threshold.
- **1.4**: `/`, `/docs`, `/openapi.json`, `/health` → 200. Lighthouse ≥ 95.
- **1.5**: 500k entries en <10 min con 4 workers. Infection MSI ≥ 70%. 0 parse_errors inesperados.

### Orden de merges

```
main
 └─ 1.0 (foundation)                         [gate ok]
     ├─ 1.1 (parser)                         [depende de 1.0]
     │   └─ 1.2 (ingestor)                   [depende de 1.1]
     ├─ 1.3 (api)                            [depende de 1.0; paralelo a 1.1/1.2]
     └─ 1.4 (landing/docs)                   [depende de 1.0]

main (con 1.0+1.1+1.2+1.3+1.4 merged)
 └─ 1.5 (integration) [gate final]
```

### Paralelismo dentro de 1.1

Los 10 extractors son independientes. Dispatch en 2 tandas:
- **Tanda A** (5 paralelos): Tombstone, Organization, Project, Lots, Process.
- **Tanda B** (5 paralelos): Results, Terms, Criteria, Notices, Documents.

Cada subagent recibe: DTO a producir, fixture XML del nodo, referencia al spec, instrucción "TDD estricto — test rojo → implementa → verde". Entrega: extractor + test + commit.

### Estimación total

**8-10 días calendario** con 2-3 streams paralelos activos.

## Dependencias nuevas (composer)

- `spatie/laravel-query-builder` — API query builder.
- `dedoc/scramble` — auto-generación OpenAPI 3.1.
- `scalar/laravel` — UI de docs.
- `sebdesign/laravel-cloudflare-zones` — Cloudflare API wrapper.
- `pestphp/pest-plugin-benchmarks` — benchmarks.
- `infection/infection` — mutation testing (dev-only).
- `cebe/php-openapi` — validación OpenAPI spec (dev-only).

## Out of scope (explícito)

- **Frontend Vue** de ficha de contrato y ficha de empresa — Specs 2 y 3.
- **Diff entre snapshots**, detección de anomalías, panel de auditoría de fraude — Spec 4.
- **Download de documentos** (PDFs de pliegos, etc.) — se guardan las URIs pero no se descargan en Spec 1. Pendiente de spec aparte.
- **API keys / rate limiting por cuenta** — rate limit actual es solo por IP. El mecanismo de keys queda como futuro.
- **Webhooks / notificaciones push** a terceros cuando cambia un contrato seguido.
- **Internacionalización** de la landing (solo español en Spec 1).

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Reproceso total tarda más de lo estimado | Benchmarks en CI detectan regresiones antes del final. Primer dry-run en subset de 3 meses permite recalibrar threshold. |
| Extractors fallan en edge cases del XML no previstos | `parse_errors` table captura todo con fragmento XML. Revisión post dry-run revela clases de error nuevas y se añaden fixtures+tests. |
| `contract_snapshots` crece más que lo estimado (500 MB) | Columna `payload` JSON es opcional — si volumen excede, se puede migrar a tabla separada o a storage frío (S3). Nivel 3 completo en Spec 4 reevaluará. |
| Cloudflare purge rate-limit (1200 URLs/min gratis) | Job `PurgeContractUrls` agrupa y reintenta con backoff. Para reproceso masivo, al final se hace un único `purge_everything` en lugar de URL-by-URL. |
| `spatie/laravel-query-builder` permite N+1 si whitelist es laxa | Tests de performance en CI miden queries ejecutadas por endpoint con `DB::enableQueryLog()`. Threshold: máximo N queries por request según include. |
| Coverage ≥ 85% bloquea merge en PR legítimo pequeño | Se permite excepción explícita con `[skip-coverage]` en commit message, revisado en PR review. |

## Referencias

- Análisis visual del ciclo de vida: `.superpowers/brainstorm/3914-1777055859/content/lifecycle-analysis.html`
- Arquitectura visual: `.superpowers/brainstorm/3914-1777055859/content/architecture-overview.html`
- Fixture real de análisis: `storage/app/placsp/201801/extracted/licitacionesPerfilesContratanteCompleto3_20200522_234632_1.atom`
- Spec anterior de organización: `docs/superpowers/specs/2026-03-22-organization-profile-design.md`
- PLACSP documentación CODICE: `https://contrataciondelestado.es/codice/`
