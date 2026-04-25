# Contract Timeline & Data Enrichment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enrich contract data extraction from PLACSP CODICE XML to capture the full lifecycle timeline (notices/states), all contracting authority details, and document references — then display it in a professional detail page.

**Architecture:** Three new database tables (`contract_notices`, `contract_documents`, expand `contracts` with org contact fields and hierarchy). The `PlacspParser` extracts all data; `ProcessPlacspFile` persists it relationally. A new `/contracts/{id}` detail page in the frontend shows a timeline stepper, org card, documents list, and all contract data. The timeline is reconstructed from `contract_notices` (each ValidNoticeInfo = a published notice with date, representing a lifecycle event).

**Tech Stack:** Laravel 12 (PHP 8.4), Nuxt 3 (Vue 3 + Tailwind v4), MySQL/SQLite

---

## CODICE XML Notice Types → Contract Lifecycle

The timeline comes from `<cac-place-ext:ValidNoticeInfo>` elements. Each has a `NoticeTypeCode` and a publication date:

| NoticeTypeCode | Meaning | Lifecycle Phase |
|---|---|---|
| `DOC_CN` | Anuncio de licitación | PUB (En plazo) |
| `DOC_CD` | Carátula del expediente | Registro inicial |
| `DOC_CAN_ADJ` | Anuncio de adjudicación | ADJ (Adjudicada) |
| `DOC_FORM` | Anuncio de formalización | RES (Resuelta/Formalizada) |

Additional lifecycle dates come from:
- `TenderSubmissionDeadlinePeriod/EndDate` → fin de plazo de presentación
- `TenderResult/AwardDate` → fecha adjudicación
- `TenderResult/Contract/IssueDate` → fecha formalización

## File Structure

### Backend (escanerpublico-backend)

| Action | File | Responsibility |
|---|---|---|
| Create | `database/migrations/2026_03_21_100000_enrich_contracts_table.php` | Add org contact/hierarchy columns to contracts |
| Create | `database/migrations/2026_03_21_100001_create_contract_notices_table.php` | Timeline notices (ValidNoticeInfo) |
| Create | `database/migrations/2026_03_21_100002_create_contract_documents_table.php` | Legal/Technical document references |
| Create | `app/Models/ContractNotice.php` | Notice model |
| Create | `app/Models/ContractDocument.php` | Document model |
| Modify | `app/Models/Contract.php` | Add relationships + new casts |
| Modify | `app/Services/PlacspParser.php` | Extract all new fields from XML |
| Modify | `app/Jobs/ProcessPlacspFile.php` | Persist notices + documents relationally |
| Modify | `app/Http/Controllers/ContractController.php` | Eager-load relations in show(), build timeline response |

### Frontend (escanerpublico-frontend)

| Action | File | Responsibility |
|---|---|---|
| Create | `pages/contratos/[id].vue` | Full contract detail page (timeline, org card, documents, financials inline) |
| Modify | `pages/contratos.vue` | Link rows to detail page |

### Known Limitations (deferred)

- **Multi-lot contracts** (`ProcurementProjectLot`): Lots are not parsed individually. Per-lot budgets, CPV codes, and locations are ignored. Multiple `TenderResult` elements (one per lot) will only capture the first. A future iteration should add a `contract_lots` table.
- The parser logs a warning when multiple `TenderResult` elements are found.

---

## Task 1: Migration — Enrich contracts table

**Files:**
- Create: `escanerpublico-backend/database/migrations/2026_03_21_100000_enrich_contracts_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Org contact info
            $table->string('organo_nif', 20)->nullable()->after('organo_superior');
            $table->string('organo_website', 500)->nullable()->after('organo_nif');
            $table->string('organo_telefono', 30)->nullable()->after('organo_website');
            $table->string('organo_email')->nullable()->after('organo_telefono');
            $table->string('organo_direccion', 500)->nullable()->after('organo_email');
            $table->string('organo_ciudad')->nullable()->after('organo_direccion');
            $table->string('organo_cp', 10)->nullable()->after('organo_ciudad');
            $table->string('organo_tipo_code', 10)->nullable()->after('organo_cp');

            // Org hierarchy (JSON array: ["Sector Público", "OTRAS ENTIDADES...", "SEPI", "MERCASA", "Mercavalencia"])
            $table->json('organo_jerarquia')->nullable()->after('organo_tipo_code');

            // TenderingProcess extras
            $table->string('submission_method_code', 10)->nullable()->after('urgencia_code');
            $table->string('contracting_system_code', 10)->nullable()->after('submission_method_code');
            $table->date('fecha_disponibilidad_docs')->nullable()->after('fecha_presentacion_limite');
            $table->time('hora_presentacion_limite')->nullable()->after('fecha_disponibilidad_docs');

            // TenderResult extras
            $table->boolean('sme_awarded')->nullable()->after('adjudicatario_nif');
            $table->string('contrato_numero')->nullable()->after('fecha_formalizacion');

            // TenderingTerms
            $table->json('criterios_adjudicacion')->nullable()->after('contrato_numero');
            $table->string('garantia_tipo_code', 10)->nullable()->after('criterios_adjudicacion');
            $table->decimal('garantia_porcentaje', 5, 2)->nullable()->after('garantia_tipo_code');
            $table->string('idioma', 5)->nullable()->after('garantia_porcentaje');

            // Contract extension
            $table->text('opciones_descripcion')->nullable()->after('idioma');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'organo_nif', 'organo_website', 'organo_telefono', 'organo_email',
                'organo_direccion', 'organo_ciudad', 'organo_cp', 'organo_tipo_code',
                'organo_jerarquia', 'submission_method_code', 'contracting_system_code',
                'fecha_disponibilidad_docs', 'hora_presentacion_limite',
                'sme_awarded', 'contrato_numero', 'criterios_adjudicacion',
                'garantia_tipo_code', 'garantia_porcentaje', 'idioma', 'opciones_descripcion',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd C:\laragon\www\escanerpublico-backend && php artisan migrate`
Expected: Migration runs successfully

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_21_100000_enrich_contracts_table.php
git commit -m "feat: add org contact, hierarchy, and tender detail columns to contracts"
```

---

## Task 2: Migration — contract_notices table

**Files:**
- Create: `escanerpublico-backend/database/migrations/2026_03_21_100001_create_contract_notices_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            // DOC_CN, DOC_CD, DOC_CAN_ADJ, DOC_FORM
            $table->string('notice_type_code', 30)->comment('TenderingNoticeTypeCode');
            $table->date('issue_date')->nullable()->comment('Publication date');
            $table->string('publication_media')->nullable()->comment('e.g. Perfil del Contratante');

            // Optional attached document
            $table->string('document_type_code', 50)->nullable()->comment('e.g. ACTA_ADJ, ACTA_FORM');
            $table->string('document_type_name')->nullable()->comment('Human-readable doc type');
            $table->string('document_uri', 1000)->nullable();
            $table->string('document_filename')->nullable();

            $table->timestamps();

            $table->index(['contract_id', 'notice_type_code']);
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_notices');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd C:\laragon\www\escanerpublico-backend && php artisan migrate`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_21_100001_create_contract_notices_table.php
git commit -m "feat: create contract_notices table for lifecycle timeline"
```

---

## Task 3: Migration — contract_documents table

**Files:**
- Create: `escanerpublico-backend/database/migrations/2026_03_21_100002_create_contract_documents_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            // 'legal' (LegalDocumentReference) or 'technical' (TechnicalDocumentReference) or 'general'
            $table->string('type', 20);
            $table->string('name')->comment('Document filename/ID from XML');
            $table->string('uri', 1000)->nullable();
            $table->string('hash')->nullable()->comment('DocumentHash for verification');

            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd C:\laragon\www\escanerpublico-backend && php artisan migrate`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_21_100002_create_contract_documents_table.php
git commit -m "feat: create contract_documents table for legal/technical docs"
```

---

## Task 4: Eloquent Models — ContractNotice & ContractDocument

**Files:**
- Create: `escanerpublico-backend/app/Models/ContractNotice.php`
- Create: `escanerpublico-backend/app/Models/ContractDocument.php`
- Modify: `escanerpublico-backend/app/Models/Contract.php`

- [ ] **Step 1: Create ContractNotice model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractNotice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public const NOTICE_TYPE_LABELS = [
        'DOC_CN' => 'Anuncio de licitación',
        'DOC_CD' => 'Carátula del expediente',
        'DOC_CAN_ADJ' => 'Anuncio de adjudicación',
        'DOC_FORM' => 'Anuncio de formalización',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

- [ ] **Step 2: Create ContractDocument model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractDocument extends Model
{
    protected $guarded = ['id'];

    public const TYPE_LABELS = [
        'legal' => 'Documento administrativo',
        'technical' => 'Documento técnico',
        'additional' => 'Documento adicional',
        'general' => 'Documento general',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

- [ ] **Step 3: Add relationships and casts to Contract model**

In `app/Models/Contract.php`, add after the existing scopes (line ~95):

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add relationships
public function notices(): HasMany
{
    return $this->hasMany(ContractNotice::class)->orderBy('issue_date');
}

public function documents(): HasMany
{
    return $this->hasMany(ContractDocument::class);
}
```

Also update `casts()` to include new fields:

```php
protected function casts(): array
{
    return [
        'cpv_codes' => 'array',
        'organo_jerarquia' => 'array',
        'criterios_adjudicacion' => 'array',
        'importe_sin_iva' => 'decimal:2',
        'importe_con_iva' => 'decimal:2',
        'valor_estimado' => 'decimal:2',
        'importe_adjudicacion_sin_iva' => 'decimal:2',
        'importe_adjudicacion_con_iva' => 'decimal:2',
        'duracion' => 'decimal:2',
        'garantia_porcentaje' => 'decimal:2',
        'sme_awarded' => 'boolean',
        'fecha_presentacion_limite' => 'date',
        'fecha_disponibilidad_docs' => 'date',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_adjudicacion' => 'date',
        'fecha_formalizacion' => 'date',
        'synced_at' => 'datetime',
    ];
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/ContractNotice.php app/Models/ContractDocument.php app/Models/Contract.php
git commit -m "feat: add ContractNotice, ContractDocument models with relationships"
```

---

## Task 5: Enhance PlacspParser — Extract all new fields

**Files:**
- Modify: `escanerpublico-backend/app/Services/PlacspParser.php`

This is the core task. The parser must now extract:
1. Org contact info (NIF, website, phone, email, address, city, postal code, type)
2. Org hierarchy (recursive ParentLocatedParty chain)
3. TenderingProcess extras (submission method, document availability, deadline time)
4. TenderResult extras (SME indicator, contract number)
5. TenderingTerms (awarding criteria, guarantees, language)
6. ValidNoticeInfo (array of notices with dates and docs)
7. LegalDocumentReference / TechnicalDocumentReference (array of docs)

- [ ] **Step 1: Modify parseEntry() — Org contact section (lines 87-113)**

Replace the entire LocatedContractingParty parsing block. The new version extracts all Party fields including NIF, website, phone, email, address, and the full parent hierarchy chain:

```php
// Órgano de contratación
$cacExtChildren = $folder->children(self::NS_CAC_EXT);
$locatedParty = $cacExtChildren->LocatedContractingParty;
if ($locatedParty && $locatedParty->count()) {
    // ContractingPartyTypeCode
    $orgTypeCode = trim((string) $locatedParty->children(self::NS_CBC)->ContractingPartyTypeCode);
    if ($orgTypeCode) $data['organo_tipo_code'] = $orgTypeCode;

    $party = $locatedParty->children(self::NS_CAC)->Party;
    if ($party && $party->count()) {
        $partyCbc = $party->children(self::NS_CBC);

        // Website
        $website = trim((string) $partyCbc->WebsiteURI);
        if ($website) $data['organo_website'] = $website;

        // Party name
        $partyName = $party->children(self::NS_CAC)->PartyName;
        $data['organo_contratante'] = trim((string) $partyName->children(self::NS_CBC)->Name);

        // Party identifications (DIR3, NIF)
        foreach ($party->children(self::NS_CAC)->PartyIdentification as $pid) {
            $idEl = $pid->children(self::NS_CBC)->ID;
            $scheme = (string) $idEl['schemeName'];
            if ($scheme === 'DIR3') {
                $data['organo_dir3'] = trim((string) $idEl);
            } elseif ($scheme === 'NIF') {
                $data['organo_nif'] = trim((string) $idEl);
            }
        }

        // Postal address
        $address = $party->children(self::NS_CAC)->PostalAddress;
        if ($address && $address->count()) {
            $addrCbc = $address->children(self::NS_CBC);
            $data['organo_ciudad'] = trim((string) $addrCbc->CityName) ?: null;
            $data['organo_cp'] = trim((string) $addrCbc->PostalZone) ?: null;
            $addressLine = $address->children(self::NS_CAC)->AddressLine;
            if ($addressLine && $addressLine->count()) {
                $data['organo_direccion'] = trim((string) $addressLine->children(self::NS_CBC)->Line) ?: null;
            }
        }

        // Contact
        $contact = $party->children(self::NS_CAC)->Contact;
        if ($contact && $contact->count()) {
            $contactCbc = $contact->children(self::NS_CBC);
            $data['organo_telefono'] = trim((string) $contactCbc->Telephone) ?: null;
            $data['organo_email'] = trim((string) $contactCbc->ElectronicMail) ?: null;
        }
    }

    // Org hierarchy — walk the recursive ParentLocatedParty chain
    $hierarchy = [];
    $parentNode = $locatedParty->children(self::NS_CAC_EXT)->ParentLocatedParty;
    while ($parentNode && $parentNode->count()) {
        $ppName = $parentNode->children(self::NS_CAC)->PartyName;
        $name = trim((string) $ppName->children(self::NS_CBC)->Name);
        if ($name) $hierarchy[] = $name;
        $parentNode = $parentNode->children(self::NS_CAC_EXT)->ParentLocatedParty;
    }
    if ($hierarchy) {
        $data['organo_jerarquia'] = $hierarchy;
        // First parent is the direct superior
        $data['organo_superior'] = $hierarchy[0];
    }
}

if (empty($data['organo_contratante'])) {
    $data['organo_contratante'] = '';
}
```

- [ ] **Step 2: Modify parseEntry() — TenderingProcess section (lines 176-187)**

Replace the TenderingProcess block to also extract submission method, document availability period, and deadline time:

```php
// TenderingProcess
$process = $folder->children(self::NS_CAC)->TenderingProcess;
if ($process && $process->count()) {
    $procCbc = $process->children(self::NS_CBC);
    $data['procedimiento_code'] = trim((string) $procCbc->ProcedureCode) ?: null;
    $data['urgencia_code'] = trim((string) $procCbc->UrgencyCode) ?: null;
    $data['submission_method_code'] = trim((string) $procCbc->SubmissionMethodCode) ?: null;
    $data['contracting_system_code'] = trim((string) $procCbc->ContractingSystemCode) ?: null;

    // Document availability period
    $docAvail = $process->children(self::NS_CAC)->DocumentAvailabilityPeriod;
    if ($docAvail && $docAvail->count()) {
        $data['fecha_disponibilidad_docs'] = $this->dateVal($docAvail->children(self::NS_CBC)->EndDate);
    }

    // Submission deadline
    $deadline = $process->children(self::NS_CAC)->TenderSubmissionDeadlinePeriod;
    if ($deadline && $deadline->count()) {
        $dlCbc = $deadline->children(self::NS_CBC);
        $data['fecha_presentacion_limite'] = $this->dateVal($dlCbc->EndDate);
        $time = trim((string) $dlCbc->EndTime);
        if ($time) $data['hora_presentacion_limite'] = $time;
    }
}
```

- [ ] **Step 3: Modify parseEntry() — TenderResult extras (after line 225)**

Add SME indicator, contract number, and a warning for multi-lot TenderResult elements. Add this at the beginning of the existing TenderResult block and the extras after:

```php
// Warn if multiple TenderResult (multi-lot — only first is captured for now)
$tenderResults = $folder->children(self::NS_CAC)->TenderResult;
if ($tenderResults && $tenderResults->count() > 1) {
    logger()->info("PLACSP: Contrato {$data['expediente']} tiene {$tenderResults->count()} TenderResult (multi-lote). Solo se captura el primero.");
}

// Inside the existing TenderResult block, after fecha_formalizacion:

// SME indicator
$sme = trim((string) $resCbc->SMEAwardedIndicator);
if ($sme !== '') $data['sme_awarded'] = $sme === 'true';

// Contract number
$contract = $result->children(self::NS_CAC)->Contract;
if ($contract && $contract->count()) {
    $data['fecha_formalizacion'] = $this->dateVal($contract->children(self::NS_CBC)->IssueDate);
    $contractId = trim((string) $contract->children(self::NS_CBC)->ID);
    if ($contractId) $data['contrato_numero'] = $contractId;
}
```

- [ ] **Step 4: Add TenderingTerms extraction (new section after TenderResult)**

```php
// TenderingTerms
$terms = $folder->children(self::NS_CAC)->TenderingTerms;
if ($terms && $terms->count()) {
    $termsCbc = $terms->children(self::NS_CBC);

    // Language
    $lang = $terms->children(self::NS_CAC)->Language;
    if ($lang && $lang->count()) {
        $data['idioma'] = trim((string) $lang->children(self::NS_CBC)->ID) ?: null;
    }

    // Financial guarantee
    $guarantee = $terms->children(self::NS_CAC)->RequiredFinancialGuarantee;
    if ($guarantee && $guarantee->count()) {
        $gCbc = $guarantee->children(self::NS_CBC);
        $data['garantia_tipo_code'] = trim((string) $gCbc->GuaranteeTypeCode) ?: null;
        $rate = trim((string) $gCbc->AmountRate);
        if ($rate !== '') $data['garantia_porcentaje'] = (float) $rate;
    }

    // Awarding criteria
    $awardingTerms = $terms->children(self::NS_CAC)->AwardingTerms;
    if ($awardingTerms && $awardingTerms->count()) {
        $criterios = [];
        foreach ($awardingTerms->children(self::NS_CAC)->AwardingCriteria as $criteria) {
            $critCbc = $criteria->children(self::NS_CBC);
            $desc = trim((string) $critCbc->Description);
            $weight = trim((string) $critCbc->WeightNumeric);
            if ($desc) {
                $criterios[] = [
                    'description' => $desc,
                    'weight' => $weight !== '' ? (float) $weight : null,
                ];
            }
        }
        if ($criterios) $data['criterios_adjudicacion'] = $criterios;
    }

    // Contract extension options
    $extension = $folder->children(self::NS_CAC)->ProcurementProject
        ?->children(self::NS_CAC)->ContractExtension;
    if ($extension && $extension->count()) {
        $opts = trim((string) $extension->children(self::NS_CBC)->OptionsDescription);
        if ($opts) $data['opciones_descripcion'] = $opts;
    }
}
```

- [ ] **Step 5: Add ValidNoticeInfo extraction (new section)**

```php
// ValidNoticeInfo — timeline notices
$notices = [];
foreach ($folder->children(self::NS_CAC_EXT)->ValidNoticeInfo as $vni) {
    $noticeType = trim((string) $vni->children(self::NS_CBC_EXT)->NoticeTypeCode);
    if (!$noticeType) continue;

    $pubStatus = $vni->children(self::NS_CAC_EXT)->AdditionalPublicationStatus;
    if (!$pubStatus || !$pubStatus->count()) continue;

    $mediaName = trim((string) $pubStatus->children(self::NS_CBC_EXT)->PublicationMediaName);

    foreach ($pubStatus->children(self::NS_CAC_EXT)->AdditionalPublicationDocumentReference as $docRef) {
        $notice = [
            'notice_type_code' => $noticeType,
            'publication_media' => $mediaName ?: null,
            'issue_date' => $this->dateVal($docRef->children(self::NS_CBC)->IssueDate),
        ];

        // Optional document attachment
        $docTypeCode = $docRef->children(self::NS_CBC)->DocumentTypeCode;
        if ($docTypeCode && trim((string) $docTypeCode)) {
            $notice['document_type_code'] = trim((string) $docTypeCode);
            $notice['document_type_name'] = (string) ($docTypeCode['name'] ?? null) ?: null;
        }

        $attachment = $docRef->children(self::NS_CAC)->Attachment;
        if ($attachment && $attachment->count()) {
            $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
            if ($extRef && $extRef->count()) {
                $extCbc = $extRef->children(self::NS_CBC);
                $notice['document_uri'] = trim((string) $extCbc->URI) ?: null;
                $notice['document_filename'] = trim((string) $extCbc->FileName) ?: null;
            }
        }

        $notices[] = $notice;
    }
}
if ($notices) $data['_notices'] = $notices;
```

- [ ] **Step 6: Add LegalDocumentReference / TechnicalDocumentReference extraction**

```php
// Document references (Legal + Technical)
$docRefs = [];

foreach ($folder->children(self::NS_CAC)->LegalDocumentReference as $docRef) {
    $docRefs[] = $this->parseDocumentReference($docRef, 'legal');
}
foreach ($folder->children(self::NS_CAC)->TechnicalDocumentReference as $docRef) {
    $docRefs[] = $this->parseDocumentReference($docRef, 'technical');
}
foreach ($folder->children(self::NS_CAC)->AdditionalDocumentReference as $docRef) {
    $docRefs[] = $this->parseDocumentReference($docRef, 'additional');
}
// GeneralDocument (cac-place-ext)
foreach ($folder->children(self::NS_CAC_EXT)->GeneralDocument as $genDoc) {
    $ref = $genDoc->children(self::NS_CAC_EXT)->GeneralDocumentDocumentReference
        ?? $genDoc->children(self::NS_CAC)->DocumentReference
        ?? null;
    if ($ref && $ref->count()) {
        $docRefs[] = $this->parseDocumentReference($ref, 'general');
    }
}

if ($docRefs) $data['_documents'] = array_filter($docRefs);
```

- [ ] **Step 7: Add parseDocumentReference helper method to PlacspParser**

```php
private function parseDocumentReference(SimpleXMLElement $docRef, string $type): ?array
{
    $name = trim((string) $docRef->children(self::NS_CBC)->ID);
    if (!$name) return null;

    $doc = [
        'type' => $type,
        'name' => $name,
    ];

    $attachment = $docRef->children(self::NS_CAC)->Attachment;
    if ($attachment && $attachment->count()) {
        $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
        if ($extRef && $extRef->count()) {
            $extCbc = $extRef->children(self::NS_CBC);
            $doc['uri'] = trim((string) $extCbc->URI) ?: null;
            $doc['hash'] = trim((string) $extCbc->DocumentHash) ?: null;

            // For GeneralDocument, the ID is often a UUID — prefer FileName if available
            $fileName = trim((string) $extCbc->FileName);
            if ($fileName) {
                $doc['name'] = $fileName;
            }
        }
    }

    return $doc;
}
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/PlacspParser.php
git commit -m "feat: extract org contact, hierarchy, notices, documents, terms from CODICE XML"
```

---

## Task 6: Update ProcessPlacspFile — Persist notices & documents

**Files:**
- Modify: `escanerpublico-backend/app/Jobs/ProcessPlacspFile.php`

- [ ] **Step 1: Update handle() to persist related data**

Replace the `handle()` method:

```php
public function handle(PlacspParser $parser): void
{
    $content = file_get_contents($this->filePath);
    if (!$content) {
        Log::error("PLACSP: No se pudo leer {$this->filePath}");
        return;
    }

    $contracts = $parser->parseAtomFile($content);
    $upserted = 0;

    foreach ($contracts as $data) {
        if (empty($data['external_id']) || empty($data['expediente'])) {
            continue;
        }

        // Separate relational data from contract fields
        $notices = $data['_notices'] ?? [];
        $documents = $data['_documents'] ?? [];
        unset($data['_notices'], $data['_documents']);

        DB::transaction(function () use ($data, $notices, $documents) {
            $contract = Contract::updateOrCreate(
                ['external_id' => $data['external_id']],
                array_merge($data, ['synced_at' => now()]),
            );

            // Sync notices (delete old + insert new to avoid duplicates)
            if ($notices) {
                $contract->notices()->delete();
                foreach ($notices as $notice) {
                    $contract->notices()->create($notice);
                }
            }

            // Sync documents
            if ($documents) {
                $contract->documents()->delete();
                foreach ($documents as $doc) {
                    $contract->documents()->create($doc);
                }
            }
        });

        $upserted++;
    }

    Log::info("PLACSP: Procesado {$this->filePath} — {$upserted} contratos upserted");
}
```

Add imports at the top:

```php
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 2: Commit**

```bash
git add app/Jobs/ProcessPlacspFile.php
git commit -m "feat: persist contract notices and documents in ProcessPlacspFile"
```

---

## Task 7: Update API — Eager-load relations and timeline builder

**Files:**
- Modify: `escanerpublico-backend/app/Http/Controllers/ContractController.php`

- [ ] **Step 1: Update show() to eager-load relations**

```php
public function show(Contract $contract): JsonResponse
{
    $contract->load(['notices', 'documents']);

    return response()->json([
        'contract' => $contract,
        'timeline' => $this->buildTimeline($contract),
    ]);
}
```

- [ ] **Step 2: Add buildTimeline() private method**

This method reconstructs the lifecycle from notices + contract dates:

```php
private function buildTimeline(Contract $contract): array
{
    $events = [];

    // From ValidNoticeInfo notices
    foreach ($contract->notices as $notice) {
        if (!$notice->issue_date) continue;

        $events[] = [
            'date' => $notice->issue_date->toDateString(),
            'type' => $notice->notice_type_code,
            'label' => ContractNotice::NOTICE_TYPE_LABELS[$notice->notice_type_code] ?? $notice->notice_type_code,
            'status' => match ($notice->notice_type_code) {
                'DOC_CN' => 'PUB',
                'DOC_CAN_ADJ' => 'ADJ',
                'DOC_FORM' => 'RES',
                default => null,
            },
            'document_uri' => $notice->document_uri,
            'document_filename' => $notice->document_filename,
        ];
    }

    // Add submission deadline as event
    if ($contract->fecha_presentacion_limite) {
        $events[] = [
            'date' => $contract->fecha_presentacion_limite->toDateString(),
            'type' => 'DEADLINE',
            'label' => 'Fin plazo presentación',
            'status' => 'EV',
            'document_uri' => null,
            'document_filename' => null,
        ];
    }

    // Add award date if not already from notice
    if ($contract->fecha_adjudicacion) {
        $hasAwardNotice = collect($events)->contains(fn($e) => $e['type'] === 'DOC_CAN_ADJ');
        if (!$hasAwardNotice) {
            $events[] = [
                'date' => $contract->fecha_adjudicacion->toDateString(),
                'type' => 'AWARD',
                'label' => 'Adjudicación',
                'status' => 'ADJ',
                'document_uri' => null,
                'document_filename' => null,
            ];
        }
    }

    // Add formalization date
    if ($contract->fecha_formalizacion) {
        $hasFormNotice = collect($events)->contains(fn($e) => $e['type'] === 'DOC_FORM');
        if (!$hasFormNotice) {
            $events[] = [
                'date' => $contract->fecha_formalizacion->toDateString(),
                'type' => 'FORMALIZATION',
                'label' => 'Formalización',
                'status' => 'RES',
                'document_uri' => null,
                'document_filename' => null,
            ];
        }
    }

    // Sort chronologically
    usort($events, fn($a, $b) => $a['date'] <=> $b['date']);

    return $events;
}
```

Add import: `use App\Models\ContractNotice;`

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ContractController.php
git commit -m "feat: eager-load notices/documents in show(), add timeline builder"
```

---

## Task 8: Frontend — Contract detail page

**Files:**
- Create: `escanerpublico-frontend/pages/contratos/[id].vue`

- [ ] **Step 1: Create the detail page**

```vue
<script setup lang="ts">
interface ContractNotice {
  id: number
  notice_type_code: string
  issue_date: string | null
  publication_media: string | null
  document_type_code: string | null
  document_type_name: string | null
  document_uri: string | null
  document_filename: string | null
}

interface ContractDocument {
  id: number
  type: string
  name: string
  uri: string | null
  hash: string | null
}

interface TimelineEvent {
  date: string
  type: string
  label: string
  status: string | null
  document_uri: string | null
  document_filename: string | null
}

interface Contract {
  id: number
  expediente: string
  objeto: string
  organo_contratante: string
  organo_dir3: string | null
  organo_superior: string | null
  organo_nif: string | null
  organo_website: string | null
  organo_telefono: string | null
  organo_email: string | null
  organo_direccion: string | null
  organo_ciudad: string | null
  organo_cp: string | null
  organo_tipo_code: string | null
  organo_jerarquia: string[] | null
  status_code: string
  tipo_contrato_code: string | null
  subtipo_contrato_code: string | null
  procedimiento_code: string | null
  urgencia_code: string | null
  submission_method_code: string | null
  importe_con_iva: string | null
  importe_sin_iva: string | null
  valor_estimado: string | null
  importe_adjudicacion_con_iva: string | null
  importe_adjudicacion_sin_iva: string | null
  adjudicatario_nombre: string | null
  adjudicatario_nif: string | null
  sme_awarded: boolean | null
  num_ofertas: number | null
  resultado_code: string | null
  fecha_adjudicacion: string | null
  fecha_formalizacion: string | null
  fecha_presentacion_limite: string | null
  hora_presentacion_limite: string | null
  fecha_inicio: string | null
  fecha_fin: string | null
  duracion: string | null
  duracion_unidad: string | null
  cpv_codes: string[] | null
  comunidad_autonoma: string | null
  nuts_code: string | null
  lugar_ejecucion: string | null
  link: string | null
  contrato_numero: string | null
  criterios_adjudicacion: { description: string; weight: number | null }[] | null
  garantia_tipo_code: string | null
  garantia_porcentaje: string | null
  idioma: string | null
  opciones_descripcion: string | null
  notices: ContractNotice[]
  documents: ContractDocument[]
  updated_at: string
}

interface ContractResponse {
  contract: Contract
  timeline: TimelineEvent[]
}

const route = useRoute()
const { data, pending, error } = await useApi<ContractResponse>(`/contracts/${route.params.id}`, {
  lazy: true,
  server: false,
})

const contract = computed(() => data.value?.contract)
const timeline = computed(() => data.value?.timeline || [])

const statusLabels: Record<string, string> = {
  PRE: 'Anuncio previo', PUB: 'En plazo', EV: 'Pendiente adjudicación',
  ADJ: 'Adjudicada', RES: 'Resuelta', ANUL: 'Anulada',
}
const statusColors: Record<string, string> = {
  PRE: 'bg-slate-100 text-slate-700', PUB: 'bg-blue-100 text-blue-700',
  EV: 'bg-amber-100 text-amber-700', ADJ: 'bg-green-100 text-green-700',
  RES: 'bg-indigo-100 text-indigo-700', ANUL: 'bg-red-100 text-red-700',
}
const tipoLabels: Record<string, string> = {
  '1': 'Obras', '2': 'Servicios', '3': 'Suministros',
  '7': 'Gestión de servicios públicos', '8': 'Concesión de obras',
  '21': 'Concesión de servicios', '31': 'Colaboración público-privada',
  '40': 'Administrativo especial', '50': 'Privado',
}
const procedimientoLabels: Record<string, string> = {
  '1': 'Abierto', '2': 'Restringido', '3': 'Negociado sin publicidad',
  '4': 'Negociado con publicidad', '5': 'Diálogo competitivo',
  '6': 'Abierto simplificado', '100': 'Basado en acuerdo marco', '999': 'Otros',
}
const duracionUnidades: Record<string, string> = { ANN: 'años', MON: 'meses', DAY: 'días' }

const timelineStatusColors: Record<string, string> = {
  PUB: 'bg-blue-500', EV: 'bg-amber-500', ADJ: 'bg-green-500', RES: 'bg-indigo-500',
}

function fmt(val: string | null): string {
  if (!val) return '—'
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 }).format(parseFloat(val))
}

function fmtDate(val: string | null): string {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

const docTypeLabels: Record<string, string> = {
  legal: 'Administrativo', technical: 'Técnico', additional: 'Adicional', general: 'General',
}

useHead({ title: () => contract.value ? `${contract.value.expediente} — Escáner Público` : 'Contrato — Escáner Público' })
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <div class="inline-block w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin" />
        <p class="mt-3 text-sm text-gray-500">Cargando contrato...</p>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error || !contract" class="flex items-center justify-center min-h-screen">
      <div class="text-center">
        <p class="text-lg text-gray-500">No se encontró el contrato</p>
        <NuxtLink to="/contratos" class="mt-3 inline-block text-indigo-600 hover:text-indigo-700 text-sm underline">
          Volver a contratos
        </NuxtLink>
      </div>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Header -->
      <div class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-start gap-4">
            <NuxtLink to="/contratos" class="mt-1 shrink-0 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </NuxtLink>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 flex-wrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusColors[contract.status_code] || 'bg-gray-100 text-gray-700'">
                  {{ statusLabels[contract.status_code] || contract.status_code }}
                </span>
                <span class="text-sm text-gray-500">{{ contract.expediente }}</span>
              </div>
              <h1 class="mt-2 text-xl font-bold text-gray-900 leading-tight">{{ contract.objeto }}</h1>
              <p class="mt-1 text-sm text-gray-500">{{ contract.organo_contratante }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Timeline -->
        <div v-if="timeline.length" class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-5">Línea temporal</h2>
          <div class="relative">
            <!-- Horizontal line -->
            <div class="absolute top-4 left-0 right-0 h-0.5 bg-gray-200" />
            <div class="flex justify-between relative">
              <div v-for="(event, i) in timeline" :key="i" class="flex flex-col items-center text-center" :style="{ width: `${100 / timeline.length}%` }">
                <!-- Dot -->
                <div class="w-8 h-8 rounded-full border-2 border-white shadow flex items-center justify-center z-10"
                  :class="event.status ? (timelineStatusColors[event.status] || 'bg-gray-400') : 'bg-gray-400'"
                >
                  <svg v-if="event.document_uri" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <div v-else class="w-2 h-2 bg-white rounded-full" />
                </div>
                <p class="mt-2 text-xs font-medium text-gray-900 leading-tight max-w-[120px]">{{ event.label }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ fmtDate(event.date) }}</p>
                <a v-if="event.document_uri" :href="event.document_uri" target="_blank" rel="noopener" class="mt-1 text-xs text-indigo-600 hover:text-indigo-700 underline">
                  {{ event.document_filename || 'Ver documento' }}
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Col 1: Contract data + Financials -->
          <div class="lg:col-span-2 space-y-6">

            <!-- Contract details -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Datos del contrato</h2>
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                <div>
                  <dt class="text-xs text-gray-500">Tipo de contrato</dt>
                  <dd class="text-sm text-gray-900">{{ tipoLabels[contract.tipo_contrato_code || ''] || contract.tipo_contrato_code || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-xs text-gray-500">Procedimiento</dt>
                  <dd class="text-sm text-gray-900">{{ procedimientoLabels[contract.procedimiento_code || ''] || contract.procedimiento_code || '—' }}</dd>
                </div>
                <div v-if="contract.cpv_codes?.length">
                  <dt class="text-xs text-gray-500">CPV</dt>
                  <dd class="flex flex-wrap gap-1 mt-0.5">
                    <span v-for="cpv in contract.cpv_codes" :key="cpv" class="inline-flex px-1.5 py-0.5 bg-gray-100 text-xs text-gray-600 rounded">{{ cpv }}</span>
                  </dd>
                </div>
                <div v-if="contract.comunidad_autonoma">
                  <dt class="text-xs text-gray-500">Ubicación</dt>
                  <dd class="text-sm text-gray-900">{{ contract.lugar_ejecucion ? `${contract.lugar_ejecucion}, ` : '' }}{{ contract.comunidad_autonoma }}</dd>
                </div>
                <div v-if="contract.duracion">
                  <dt class="text-xs text-gray-500">Duración</dt>
                  <dd class="text-sm text-gray-900">{{ contract.duracion }} {{ duracionUnidades[contract.duracion_unidad || 'MON'] || contract.duracion_unidad }}</dd>
                </div>
                <div v-if="contract.idioma">
                  <dt class="text-xs text-gray-500">Idioma</dt>
                  <dd class="text-sm text-gray-900">{{ contract.idioma?.toUpperCase() }}</dd>
                </div>
                <div v-if="contract.opciones_descripcion" class="sm:col-span-2">
                  <dt class="text-xs text-gray-500">Opciones/prórrogas</dt>
                  <dd class="text-sm text-gray-900">{{ contract.opciones_descripcion }}</dd>
                </div>
              </dl>
            </div>

            <!-- Financials -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Importes</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Budget -->
                <div class="space-y-3">
                  <h3 class="text-xs font-medium text-gray-500">Presupuesto</h3>
                  <div v-if="contract.importe_con_iva">
                    <p class="text-2xl font-bold text-gray-900">{{ fmt(contract.importe_con_iva) }}</p>
                    <p class="text-xs text-gray-500">IVA incluido</p>
                  </div>
                  <div v-if="contract.importe_sin_iva">
                    <p class="text-sm text-gray-700">{{ fmt(contract.importe_sin_iva) }} <span class="text-xs text-gray-400">sin IVA</span></p>
                  </div>
                  <div v-if="contract.valor_estimado">
                    <p class="text-sm text-gray-700">{{ fmt(contract.valor_estimado) }} <span class="text-xs text-gray-400">valor estimado</span></p>
                  </div>
                </div>
                <!-- Award -->
                <div v-if="contract.importe_adjudicacion_con_iva" class="space-y-3">
                  <h3 class="text-xs font-medium text-gray-500">Adjudicación</h3>
                  <div>
                    <p class="text-2xl font-bold text-green-700">{{ fmt(contract.importe_adjudicacion_con_iva) }}</p>
                    <p class="text-xs text-gray-500">IVA incluido</p>
                  </div>
                  <div v-if="contract.importe_adjudicacion_sin_iva">
                    <p class="text-sm text-gray-700">{{ fmt(contract.importe_adjudicacion_sin_iva) }} <span class="text-xs text-gray-400">sin IVA</span></p>
                  </div>
                </div>
              </div>
              <!-- Guarantee -->
              <div v-if="contract.garantia_porcentaje" class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500">Garantía definitiva: <span class="text-sm text-gray-900 font-medium">{{ contract.garantia_porcentaje }}%</span></p>
              </div>
            </div>

            <!-- Award criteria -->
            <div v-if="contract.criterios_adjudicacion?.length" class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Criterios de adjudicación</h2>
              <div class="space-y-2">
                <div v-for="(crit, i) in contract.criterios_adjudicacion" :key="i" class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                  <span class="text-sm text-gray-900">{{ crit.description }}</span>
                  <span v-if="crit.weight != null" class="text-sm font-semibold text-indigo-600 shrink-0 ml-4">{{ crit.weight }}%</span>
                </div>
              </div>
            </div>

            <!-- Documents -->
            <div v-if="contract.documents?.length" class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Documentación</h2>
              <div class="divide-y divide-gray-100">
                <div v-for="doc in contract.documents" :key="doc.id" class="flex items-center justify-between py-3">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center" :class="doc.type === 'legal' ? 'bg-blue-50 text-blue-600' : doc.type === 'technical' ? 'bg-amber-50 text-amber-600' : 'bg-gray-50 text-gray-600'">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm text-gray-900 truncate">{{ doc.name }}</p>
                      <p class="text-xs text-gray-500">{{ docTypeLabels[doc.type] || doc.type }}</p>
                    </div>
                  </div>
                  <a v-if="doc.uri" :href="doc.uri" target="_blank" rel="noopener" class="shrink-0 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                    Descargar
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Col 2: Sidebar -->
          <div class="space-y-6">

            <!-- Award info -->
            <div v-if="contract.adjudicatario_nombre" class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Adjudicación</h2>
              <dl class="space-y-3">
                <div>
                  <dt class="text-xs text-gray-500">Adjudicatario</dt>
                  <dd class="text-sm font-medium text-gray-900">{{ contract.adjudicatario_nombre }}</dd>
                  <dd v-if="contract.adjudicatario_nif" class="text-xs text-gray-500 mt-0.5">NIF: {{ contract.adjudicatario_nif }}</dd>
                  <dd v-if="contract.sme_awarded" class="mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">PYME</span>
                  </dd>
                </div>
                <div v-if="contract.num_ofertas">
                  <dt class="text-xs text-gray-500">Ofertas recibidas</dt>
                  <dd class="text-sm text-gray-900">{{ contract.num_ofertas }}</dd>
                </div>
                <div v-if="contract.fecha_adjudicacion">
                  <dt class="text-xs text-gray-500">Fecha adjudicación</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_adjudicacion) }}</dd>
                </div>
                <div v-if="contract.fecha_formalizacion">
                  <dt class="text-xs text-gray-500">Fecha formalización</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_formalizacion) }}</dd>
                </div>
                <div v-if="contract.contrato_numero">
                  <dt class="text-xs text-gray-500">N.º contrato</dt>
                  <dd class="text-sm text-gray-900">{{ contract.contrato_numero }}</dd>
                </div>
              </dl>
            </div>

            <!-- Plazos -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Plazos</h2>
              <dl class="space-y-3">
                <div v-if="contract.fecha_presentacion_limite">
                  <dt class="text-xs text-gray-500">Presentación ofertas</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_presentacion_limite) }}
                    <span v-if="contract.hora_presentacion_limite" class="text-xs text-gray-500">{{ contract.hora_presentacion_limite }}</span>
                  </dd>
                </div>
                <div v-if="contract.fecha_disponibilidad_docs">
                  <dt class="text-xs text-gray-500">Documentos disponibles hasta</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_disponibilidad_docs) }}</dd>
                </div>
                <div v-if="contract.fecha_inicio">
                  <dt class="text-xs text-gray-500">Inicio ejecución</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_inicio) }}</dd>
                </div>
                <div v-if="contract.fecha_fin">
                  <dt class="text-xs text-gray-500">Fin ejecución</dt>
                  <dd class="text-sm text-gray-900">{{ fmtDate(contract.fecha_fin) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Órgano de contratación -->
            <div class="bg-white rounded-xl border border-gray-200 p-6">
              <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Órgano de contratación</h2>
              <p class="text-sm font-medium text-gray-900">{{ contract.organo_contratante }}</p>
              <p v-if="contract.organo_nif" class="text-xs text-gray-500 mt-0.5">NIF: {{ contract.organo_nif }}</p>

              <!-- Hierarchy breadcrumb -->
              <div v-if="contract.organo_jerarquia?.length" class="mt-3 flex flex-wrap items-center gap-1 text-xs text-gray-500">
                <template v-for="(org, i) in [...contract.organo_jerarquia].reverse()" :key="i">
                  <span v-if="i > 0" class="text-gray-300">/</span>
                  <span>{{ org }}</span>
                </template>
              </div>

              <!-- Contact details -->
              <div class="mt-4 pt-3 border-t border-gray-100 space-y-2">
                <div v-if="contract.organo_direccion" class="flex items-start gap-2 text-sm text-gray-600">
                  <svg class="w-4 h-4 mt-0.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                  <span>{{ contract.organo_direccion }}<template v-if="contract.organo_cp || contract.organo_ciudad">, {{ contract.organo_cp }} {{ contract.organo_ciudad }}</template></span>
                </div>
                <div v-if="contract.organo_telefono" class="flex items-center gap-2 text-sm text-gray-600">
                  <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                  <span>{{ contract.organo_telefono }}</span>
                </div>
                <div v-if="contract.organo_email" class="flex items-center gap-2 text-sm">
                  <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                  <a :href="`mailto:${contract.organo_email}`" class="text-indigo-600 hover:text-indigo-700">{{ contract.organo_email }}</a>
                </div>
                <div v-if="contract.organo_website" class="flex items-center gap-2 text-sm">
                  <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                  <a :href="contract.organo_website" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-700 truncate">{{ contract.organo_website }}</a>
                </div>
              </div>
            </div>

            <!-- External link -->
            <a
              :href="contract.link || `https://contrataciondelestado.es/wps/poc?uri=deeplink:detalle_licitacion&idEvl=${contract.id}`"
              target="_blank" rel="noopener"
              class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-xl hover:bg-indigo-100 transition-colors border border-indigo-100"
            >
              Ver en PLACSP
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            </a>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
cd C:\laragon\www\escanerpublico-frontend
git add pages/contratos/\[id\].vue
git commit -m "feat: add contract detail page with timeline, org card, documents"
```

---

## Task 9: Link list rows to detail page

**Files:**
- Modify: `escanerpublico-frontend/pages/contratos.vue`

- [ ] **Step 1: Add navigateToDetail function and modify row click**

In the `<script setup>` section, add:

```typescript
const router = useRouter()

function navigateToDetail(id: number) {
  router.push(`/contratos/${id}`)
}
```

- [ ] **Step 2: Replace row @click from toggleExpand to navigateToDetail**

Change the main `<tr>` click handler (line ~281):

```html
@click="navigateToDetail(c.id)"
```

Remove the `expandedId` ref, `toggleExpand()`, the expanded detail `<tr>`, and all expand-related code since the detail page now handles this.

- [ ] **Step 3: Commit**

```bash
cd C:\laragon\www\escanerpublico-frontend
git add pages/contratos.vue
git commit -m "feat: link contract rows to detail page, remove inline expansion"
```

---

## Task 10: Re-sync existing data

- [ ] **Step 1: Re-run the sync to populate new fields**

Run: `cd C:\laragon\www\escanerpublico-backend && php artisan contracts:sync --all --sync`

This will re-process all existing ATOM files and populate the new fields, notices, and documents.

- [ ] **Step 2: Verify data**

Run: `cd C:\laragon\www\escanerpublico-backend && php artisan tinker --execute="echo 'Notices: ' . \App\Models\ContractNotice::count() . PHP_EOL . 'Documents: ' . \App\Models\ContractDocument::count() . PHP_EOL . 'With hierarchy: ' . \App\Models\Contract::whereNotNull('organo_jerarquia')->count();"`

Expected: Non-zero counts for all three.

- [ ] **Step 3: Manual test**

Open `http://localhost:3000/contratos`, click any contract row. Verify:
- Timeline renders with chronological events
- Org card shows contact info and hierarchy breadcrumb
- Documents section lists downloadable files
- Financials show both budget and award amounts
- All dates are formatted correctly

---

## Summary of Changes

### Backend
| File | Change |
|---|---|
| 3 new migrations | Enrich contracts + create contract_notices + create contract_documents |
| 2 new models | ContractNotice, ContractDocument |
| Contract.php | Add relationships, casts for new fields |
| PlacspParser.php | Extract ~20 new fields + notices + documents from XML |
| ProcessPlacspFile.php | Persist notices and documents relationally |
| ContractController.php | Eager-load relations, build timeline response |

### Frontend
| File | Change |
|---|---|
| pages/contratos/[id].vue | New full detail page with timeline, org card, documents |
| pages/contratos.vue | Link rows to detail page instead of inline expansion |

### New Database Tables
- `contract_notices` — lifecycle timeline events (from ValidNoticeInfo)
- `contract_documents` — legal/technical document references

### New Contract Fields (20+)
Org contact (NIF, website, phone, email, address, city, postal code, type), hierarchy (JSON), tendering extras (submission method, doc availability date, deadline time), TenderResult extras (SME, contract number), TenderingTerms (criteria JSON, guarantee, language, extension options)
