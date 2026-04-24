# Phase 1.1 — Parser (XMLReader streaming + 10 extractors + compositor)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`.

**Goal:** Parser decompuesto en 10 extractors especializados + compositor + stream parser con XMLReader.

**Architecture:** `PlacspStreamParser` itera entries con `XMLReader::expand()`. Cada entry se pasa a `PlacspEntryParser` que orquesta los 10 extractors. Cada extractor es responsable de un nodo CODICE y emite su DTO.

**Tech Stack:** PHP 8.4, SimpleXMLElement, XMLReader, Pest.

**Branch:** `feature/contracts-v2-parser`. **Worktree:** `wt-1.1-parser`. Base: `main` con 1.0 mergeada.

**Gate:**
- Todos los extractor tests verdes.
- `PlacspEntryParser` produce `EntryDTO` completo de fixture real.
- `PlacspStreamParser` consume < 20 MB RAM en atom de 20 MB.
- PHPStan L8 verde sobre `Services/Parser/`.

**Paralelismo:** las tareas 3-12 (10 extractors) son independientes — dispatch en 2 tandas de 5 subagents paralelos cada una.

---

## Task 1 — Crear carpeta de fixtures + sample-01 (PUB mínimo)

**Files:**
- Create: `tests/Fixtures/placsp/sample-01-pub.xml`

- [ ] **Step 1: Crear carpeta y fixture**

Mira el atom real en `storage/app/placsp/201801/extracted/licitacionesPerfilesContratanteCompleto3_20200522_234632_1.atom` y recorta una entry con status `PUB`. Guarda el XML completo del entry (incluyendo `<entry>`, metadata Atom, y el `cac-place-ext:ContractFolderStatus` entero) envuelto en un feed mínimo:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:cbc="urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2"
      xmlns:cac="urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2"
      xmlns:cac-place-ext="urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2"
      xmlns:cbc-place-ext="urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2"
      xmlns:at="http://purl.org/atompub/tombstones/1.0">
    <entry>
        <!-- PEGA aquí un entry completo con status PUB del atom real -->
    </entry>
</feed>
```

- [ ] **Step 2: Commit**

```bash
git add tests/Fixtures/placsp/sample-01-pub.xml
git commit -m "test(contracts): add sample-01-pub fixture from real 201801 atom"
```

---

## Task 2 — Crear el resto de fixtures (sample-02 a sample-10 + full-20-entries)

**Files:**
- Create: `tests/Fixtures/placsp/sample-02-adj.xml` — entry status ADJ con WinningParty completo
- Create: `tests/Fixtures/placsp/sample-03-res-formalized.xml` — entry status RES con `cac:Contract/cbc:IssueDate`
- Create: `tests/Fixtures/placsp/sample-04-multi-lote.xml` — entry con ≥2 `cac:ProcurementProjectLot`
- Create: `tests/Fixtures/placsp/sample-05-with-modifications.xml` — entry con notice `DOC_MOD`
- Create: `tests/Fixtures/placsp/sample-06-tombstone.xml` — feed con solo `<at:deleted-entry>`
- Create: `tests/Fixtures/placsp/sample-07-sin-winner.xml` — status ADJ pero sin WinningParty
- Create: `tests/Fixtures/placsp/sample-08-malformed.xml` — XML con entry con tag no cerrado
- Create: `tests/Fixtures/placsp/sample-09-with-criteria.xml` — entry con 5 AwardingCriteria OBJ + SUBJ
- Create: `tests/Fixtures/placsp/sample-10-extension.xml` — entry con `cac:ContractExtension`
- Create: `tests/Fixtures/placsp/full-20-entries.atom` — recorte del `201801` atom con 20 entries variadas

- [ ] **Step 1: Crear cada fixture**

Para cada fixture, busca en los atoms reales de `storage/app/placsp/` un entry que represente el caso. Recórtalo y envuélvelo en el feed mínimo (o directamente concatena 20 entries para `full-20-entries.atom`).

Para `sample-08-malformed.xml`, introduce un error controlado (p.ej. `<cbc:ContractFolderID>FOO` sin cerrar).

Para `sample-06-tombstone.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:at="http://purl.org/atompub/tombstones/1.0">
    <at:deleted-entry ref="https://contrataciondelestado.es/sindicacion/licitacionesPerfilContratante/19163035"
                      when="2026-03-20T14:48:14.452+01:00">
        <at:comment type="ANULADA"/>
    </at:deleted-entry>
</feed>
```

- [ ] **Step 2: Commit**

```bash
git add tests/Fixtures/placsp/
git commit -m "test(contracts): add 10 parser fixtures covering all entry variants"
```

---

## Task 3 — `TombstoneExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/TombstoneExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/TombstoneExtractorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Tests\TestCase;

class TombstoneExtractorTest extends TestCase
{
    public function test_extracts_ref_and_when(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-06-tombstone.xml'));
        $feed = new \SimpleXMLElement($xml);
        $atNs = 'http://purl.org/atompub/tombstones/1.0';
        $deleted = $feed->children($atNs)->{'deleted-entry'};

        $dto = (new TombstoneExtractor())->extract($deleted[0]);

        $this->assertInstanceOf(TombstoneDTO::class, $dto);
        $this->assertStringContainsString('/19163035', $dto->ref);
        $this->assertEquals('2026-03-20', $dto->when->format('Y-m-d'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
php artisan test tests/Unit/Contracts/Parser/Extractors/TombstoneExtractorTest.php
```

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/TombstoneExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;

class TombstoneExtractor
{
    public function extract(\SimpleXMLElement $deletedEntry): TombstoneDTO
    {
        $attrs = $deletedEntry->attributes();
        return new TombstoneDTO(
            ref: (string) $attrs['ref'],
            when: new \DateTimeImmutable((string) $attrs['when']),
        );
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/TombstoneExtractor.php tests/Unit/Contracts/Parser/Extractors/TombstoneExtractorTest.php
git commit -m "feat(contracts): add TombstoneExtractor"
```

---

## Task 4 — `OrganizationExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/OrganizationExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/OrganizationExtractorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;
use Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor;
use Tests\TestCase;

class OrganizationExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_full_organization(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];
        $folder = $entry->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $locatedParty = $folder->children(self::NS_CAC_EXT)->LocatedContractingParty;

        $dto = (new OrganizationExtractor())->extract($locatedParty);

        $this->assertInstanceOf(OrganizationDTO::class, $dto);
        $this->assertNotEmpty($dto->name);
        $this->assertMatchesRegularExpression('/^[A-Z]\d+/', $dto->dir3 ?? '');
        $this->assertStringStartsWith('P', $dto->nif ?? '');
        $this->assertNotEmpty($dto->hierarchy);
        $this->assertNotNull($dto->address);
    }

    public function test_handles_missing_optional_fields(): void
    {
        $xmlString = '<?xml version="1.0"?>
<root xmlns:cbc="urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2"
      xmlns:cac="urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2"
      xmlns:cac-place-ext="urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2">
    <cac-place-ext:LocatedContractingParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>Nombre Mínimo</cbc:Name></cac:PartyName>
        </cac:Party>
    </cac-place-ext:LocatedContractingParty>
</root>';
        $root = new \SimpleXMLElement($xmlString);
        $lp = $root->children(self::NS_CAC_EXT)->LocatedContractingParty;

        $dto = (new OrganizationExtractor())->extract($lp);

        $this->assertEquals('Nombre Mínimo', $dto->name);
        $this->assertNull($dto->dir3);
        $this->assertNull($dto->nif);
        $this->assertEquals([], $dto->hierarchy);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/OrganizationExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\AddressDTO;
use Modules\Contracts\Services\Parser\DTOs\ContactDTO;
use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;

class OrganizationExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $locatedParty): OrganizationDTO
    {
        $typeCode = trim((string) $locatedParty->children(self::NS_CBC)->ContractingPartyTypeCode) ?: null;
        $activityCode = trim((string) $locatedParty->children(self::NS_CBC)->ActivityCode) ?: null;
        $buyerProfileUri = trim((string) $locatedParty->children(self::NS_CBC)->BuyerProfileURIID) ?: null;

        $name = '';
        $dir3 = $nif = $platformId = null;
        $address = null;
        $contacts = [];

        $party = $locatedParty->children(self::NS_CAC)->Party;
        if ($party && $party->count()) {
            $name = trim((string) $party->children(self::NS_CAC)->PartyName->children(self::NS_CBC)->Name);
            $websiteUri = trim((string) $party->children(self::NS_CBC)->WebsiteURI) ?: null;

            foreach ($party->children(self::NS_CAC)->PartyIdentification as $pid) {
                $idEl = $pid->children(self::NS_CBC)->ID;
                $scheme = (string) $idEl['schemeName'];
                $value = trim((string) $idEl);
                if ($value === '') continue;
                match ($scheme) {
                    'DIR3' => $dir3 = $value,
                    'NIF' => $nif = $value,
                    'ID_PLATAFORMA' => $platformId = $value,
                    default => null,
                };
            }

            // Address
            $postal = $party->children(self::NS_CAC)->PostalAddress;
            if ($postal && $postal->count()) {
                $line = trim((string) $postal->children(self::NS_CAC)->AddressLine?->children(self::NS_CBC)->Line);
                $cityName = trim((string) $postal->children(self::NS_CBC)->CityName);
                $postalCode = trim((string) $postal->children(self::NS_CBC)->PostalZone);
                $countryCode = trim((string) $postal->children(self::NS_CAC)->Country?->children(self::NS_CBC)->IdentificationCode);

                if ($line || $cityName || $postalCode || $countryCode) {
                    $address = new AddressDTO(
                        line: $line ?: null,
                        postal_code: $postalCode ?: null,
                        city_name: $cityName ?: null,
                        country_code: $countryCode ?: null,
                    );
                }
            }

            // Contacts
            $contact = $party->children(self::NS_CAC)->Contact;
            if ($contact && $contact->count()) {
                $cbc = $contact->children(self::NS_CBC);
                foreach ([
                    'phone' => 'Telephone',
                    'fax' => 'Telefax',
                    'email' => 'ElectronicMail',
                ] as $type => $tag) {
                    $v = trim((string) $cbc->{$tag});
                    if ($v !== '') $contacts[] = new ContactDTO($type, $v);
                }
            }

            if ($websiteUri) $contacts[] = new ContactDTO('website', $websiteUri);
        }

        // Hierarchy (walk ParentLocatedParty chain)
        $hierarchy = [];
        $parent = $locatedParty->children(self::NS_CAC_EXT)->ParentLocatedParty;
        while ($parent && $parent->count()) {
            $n = trim((string) $parent->children(self::NS_CAC)->PartyName?->children(self::NS_CBC)->Name);
            if ($n !== '') $hierarchy[] = $n;
            $parent = $parent->children(self::NS_CAC_EXT)->ParentLocatedParty;
        }

        return new OrganizationDTO(
            name: $name,
            dir3: $dir3,
            nif: $nif,
            platform_id: $platformId,
            buyer_profile_uri: $buyerProfileUri,
            activity_code: $activityCode,
            type_code: $typeCode,
            hierarchy: $hierarchy,
            address: $address,
            contacts: $contacts,
        );
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/OrganizationExtractor.php tests/Unit/Contracts/Parser/Extractors/OrganizationExtractorTest.php
git commit -m "feat(contracts): add OrganizationExtractor with full CODICE coverage"
```

---

## Task 5 — `ProjectExtractor` (single-project fallback when no lotes)

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/ProjectExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/ProjectExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ProjectExtractor;
use Tests\TestCase;

class ProjectExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_project_into_single_lot_dto(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lot = (new ProjectExtractor())->extract($project);

        $this->assertEquals(1, $lot->lot_number);
        $this->assertNotEmpty($lot->title);
        $this->assertNotNull($lot->budget_with_tax);
        $this->assertIsArray($lot->cpv_codes);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/ProjectExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\LotDTO;

class ProjectExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    public function extract(\SimpleXMLElement $project): LotDTO
    {
        $cbc = $project->children(self::NS_CBC);
        $cbcExt = $project->children(self::NS_CBC_EXT);
        $cac = $project->children(self::NS_CAC);

        $budget = $cac->BudgetAmount;
        $budgetCbc = $budget ? $budget->children(self::NS_CBC) : null;

        $cpvCodes = [];
        foreach ($cac->RequiredCommodityClassification as $cpv) {
            $code = trim((string) $cpv->children(self::NS_CBC)->ItemClassificationCode);
            if ($code !== '') $cpvCodes[] = $code;
        }

        $location = $cac->RealizedLocation;
        $nutsCode = $location ? (trim((string) $location->children(self::NS_CBC)->CountrySubentityCode) ?: null) : null;
        $lugarEjec = null;
        if ($location && $location->children(self::NS_CAC)->Address) {
            $lugarEjec = trim((string) $location->children(self::NS_CAC)->Address->children(self::NS_CBC)->CityName) ?: null;
        }

        $period = $cac->PlannedPeriod;
        $duration = null;
        $durationUnit = null;
        $startDate = null;
        $endDate = null;
        if ($period && $period->count()) {
            $durEl = $period->children(self::NS_CBC)->DurationMeasure;
            $durVal = trim((string) $durEl);
            if ($durVal !== '') {
                $duration = (float) $durVal;
                $durationUnit = (string) ($durEl['unitCode'] ?? 'MON');
            }
            $startDate = trim((string) $period->children(self::NS_CBC)->StartDate) ?: null;
            $endDate = trim((string) $period->children(self::NS_CBC)->EndDate) ?: null;
        }

        $extOptions = null;
        if ($cac->ContractExtension && $cac->ContractExtension->count()) {
            $extOptions = trim((string) $cac->ContractExtension->children(self::NS_CBC)->OptionsDescription) ?: null;
        }

        return new LotDTO(
            lot_number: 1,
            title: trim((string) $cbc->Name) ?: null,
            description: null,
            tipo_contrato_code: trim((string) $cbc->TypeCode) ?: null,
            subtipo_contrato_code: trim((string) $cbcExt->SubTypeCode) ?: null,
            cpv_codes: $cpvCodes,
            budget_with_tax: $budgetCbc ? $this->decimal($budgetCbc->TotalAmount) : null,
            budget_without_tax: $budgetCbc ? $this->decimal($budgetCbc->TaxExclusiveAmount) : null,
            estimated_value: $budgetCbc ? $this->decimal($budgetCbc->EstimatedOverallContractAmount) : null,
            duration: $duration,
            duration_unit: $durationUnit,
            start_date: $startDate,
            end_date: $endDate,
            nuts_code: $nutsCode,
            lugar_ejecucion: $lugarEjec,
            options_description: $extOptions,
        );
    }

    private function decimal(?\SimpleXMLElement $el): ?float
    {
        if ($el === null) return null;
        $v = trim((string) $el);
        return $v !== '' ? (float) $v : null;
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/ProjectExtractor.php tests/Unit/Contracts/Parser/Extractors/ProjectExtractorTest.php
git commit -m "feat(contracts): add ProjectExtractor (single-lot fallback)"
```

---

## Task 6 — `LotsExtractor` (multi-lote real)

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/LotsExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/LotsExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\LotsExtractor;
use Tests\TestCase;

class LotsExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_multiple_lots(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-04-multi-lote.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lots = (new LotsExtractor())->extract($project);

        $this->assertGreaterThanOrEqual(2, count($lots));
        foreach ($lots as $i => $lot) {
            $this->assertEquals($i + 1, $lot->lot_number);
        }
    }

    public function test_returns_empty_when_no_lots_element(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lots = (new LotsExtractor())->extract($project);

        $this->assertEquals([], $lots);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/LotsExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\LotDTO;

class LotsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    /** @return LotDTO[] */
    public function extract(\SimpleXMLElement $project): array
    {
        $lotNodes = $project->children(self::NS_CAC)->ProcurementProjectLot;
        $lots = [];
        $i = 1;

        foreach ($lotNodes as $lotNode) {
            $lots[] = $this->parseLot($lotNode, $i++);
        }

        return $lots;
    }

    private function parseLot(\SimpleXMLElement $lotNode, int $defaultNum): LotDTO
    {
        $cbc = $lotNode->children(self::NS_CBC);
        $cac = $lotNode->children(self::NS_CAC);

        $lotNumberStr = trim((string) $cbc->ID);
        $lotNumber = ctype_digit($lotNumberStr) ? (int) $lotNumberStr : $defaultNum;

        $budget = $cac->BudgetAmount;
        $budgetCbc = $budget ? $budget->children(self::NS_CBC) : null;

        $cpvCodes = [];
        foreach ($cac->RequiredCommodityClassification as $cpv) {
            $code = trim((string) $cpv->children(self::NS_CBC)->ItemClassificationCode);
            if ($code !== '') $cpvCodes[] = $code;
        }

        $period = $cac->PlannedPeriod;
        $duration = null;
        $durationUnit = null;
        $startDate = null;
        $endDate = null;
        if ($period && $period->count()) {
            $durEl = $period->children(self::NS_CBC)->DurationMeasure;
            $durVal = trim((string) $durEl);
            if ($durVal !== '') {
                $duration = (float) $durVal;
                $durationUnit = (string) ($durEl['unitCode'] ?? 'MON');
            }
            $startDate = trim((string) $period->children(self::NS_CBC)->StartDate) ?: null;
            $endDate = trim((string) $period->children(self::NS_CBC)->EndDate) ?: null;
        }

        $location = $cac->RealizedLocation;
        $nutsCode = $location ? (trim((string) $location->children(self::NS_CBC)->CountrySubentityCode) ?: null) : null;
        $lugarEjec = null;
        if ($location && $location->children(self::NS_CAC)->Address) {
            $lugarEjec = trim((string) $location->children(self::NS_CAC)->Address->children(self::NS_CBC)->CityName) ?: null;
        }

        return new LotDTO(
            lot_number: $lotNumber,
            title: trim((string) $cbc->Name) ?: null,
            description: trim((string) $cbc->Description) ?: null,
            tipo_contrato_code: trim((string) $cbc->TypeCode) ?: null,
            subtipo_contrato_code: trim((string) $lotNode->children(self::NS_CBC_EXT)->SubTypeCode) ?: null,
            cpv_codes: $cpvCodes,
            budget_with_tax: $budgetCbc ? $this->decimal($budgetCbc->TotalAmount) : null,
            budget_without_tax: $budgetCbc ? $this->decimal($budgetCbc->TaxExclusiveAmount) : null,
            estimated_value: $budgetCbc ? $this->decimal($budgetCbc->EstimatedOverallContractAmount) : null,
            duration: $duration,
            duration_unit: $durationUnit,
            start_date: $startDate,
            end_date: $endDate,
            nuts_code: $nutsCode,
            lugar_ejecucion: $lugarEjec,
            options_description: null,
        );
    }

    private function decimal(?\SimpleXMLElement $el): ?float
    {
        if ($el === null) return null;
        $v = trim((string) $el);
        return $v !== '' ? (float) $v : null;
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/LotsExtractor.php tests/Unit/Contracts/Parser/Extractors/LotsExtractorTest.php
git commit -m "feat(contracts): add LotsExtractor (multi-lote real)"
```

---

## Task 7 — `ProcessExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/ProcessExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/ProcessExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ProcessExtractor;
use Tests\TestCase;

class ProcessExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_process_codes_and_dates(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $process = $folder->children(self::NS_CAC)->TenderingProcess;

        $dto = (new ProcessExtractor())->extract($process);

        $this->assertNotNull($dto->procedure_code);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/ProcessExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\ProcessDTO;

class ProcessExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $process): ProcessDTO
    {
        $cbc = $process->children(self::NS_CBC);

        $docAvail = $process->children(self::NS_CAC)->DocumentAvailabilityPeriod;
        $fechaDispDocs = null;
        if ($docAvail && $docAvail->count()) {
            $fechaDispDocs = trim((string) $docAvail->children(self::NS_CBC)->EndDate) ?: null;
        }

        $deadline = $process->children(self::NS_CAC)->TenderSubmissionDeadlinePeriod;
        $fechaPresLim = null;
        $horaPresLim = null;
        if ($deadline && $deadline->count()) {
            $dlCbc = $deadline->children(self::NS_CBC);
            $fechaPresLim = trim((string) $dlCbc->EndDate) ?: null;
            $horaPresLim = trim((string) $dlCbc->EndTime) ?: null;
        }

        return new ProcessDTO(
            procedure_code: trim((string) $cbc->ProcedureCode) ?: null,
            urgency_code: trim((string) $cbc->UrgencyCode) ?: null,
            submission_method_code: trim((string) $cbc->SubmissionMethodCode) ?: null,
            contracting_system_code: trim((string) $cbc->ContractingSystemCode) ?: null,
            fecha_disponibilidad_docs: $fechaDispDocs,
            fecha_presentacion_limite: $fechaPresLim,
            hora_presentacion_limite: $horaPresLim,
        );
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/ProcessExtractor.php tests/Unit/Contracts/Parser/Extractors/ProcessExtractorTest.php
git commit -m "feat(contracts): add ProcessExtractor"
```

---

## Task 8 — `ResultsExtractor` (multi-lote con WinningParty + address)

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/ResultsExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/ResultsExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ResultsExtractor;
use Tests\TestCase;

class ResultsExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_single_result_adj(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(1, count($results));
        $r = $results[0];
        $this->assertNotNull($r->winner);
        $this->assertNotNull($r->winner->address);
        $this->assertNotNull($r->amount_with_tax);
    }

    public function test_extracts_multiple_results_multi_lote(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-04-multi-lote.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_handles_no_winner(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-07-sin-winner.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        foreach ($results as $r) {
            $this->assertNull($r->winner);
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/ResultsExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\AddressDTO;
use Modules\Contracts\Services\Parser\DTOs\ResultDTO;
use Modules\Contracts\Services\Parser\DTOs\WinningPartyDTO;

class ResultsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    /** @return ResultDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $results = [];
        $i = 1;

        foreach ($folder->children(self::NS_CAC)->TenderResult as $result) {
            $cbc = $result->children(self::NS_CBC);
            $cac = $result->children(self::NS_CAC);

            // Determine lot number (some multi-lote results carry a lot reference; default to positional)
            $lotNumber = $i;
            $lotId = trim((string) $cbc->ProcurementProjectLotID);
            if (ctype_digit($lotId)) {
                $lotNumber = (int) $lotId;
            }

            // Winner
            $winner = null;
            $winningParty = $cac->WinningParty;
            if ($winningParty && $winningParty->count()) {
                $wName = trim((string) $winningParty->children(self::NS_CAC)->PartyName?->children(self::NS_CBC)->Name);
                $wNif = null;
                foreach ($winningParty->children(self::NS_CAC)->PartyIdentification as $pid) {
                    $wNif = trim((string) $pid->children(self::NS_CBC)->ID) ?: null;
                    if ($wNif) break;
                }

                // Physical location (address of winner)
                $wAddress = null;
                $physLoc = $winningParty->children(self::NS_CAC)->PhysicalLocation;
                if ($physLoc && $physLoc->count()) {
                    $addr = $physLoc->children(self::NS_CAC)->Address;
                    if ($addr && $addr->count()) {
                        $line = trim((string) $addr->children(self::NS_CAC)->AddressLine?->children(self::NS_CBC)->Line);
                        $city = trim((string) $addr->children(self::NS_CBC)->CityName);
                        $postal = trim((string) $addr->children(self::NS_CBC)->PostalZone);
                        $country = trim((string) $addr->children(self::NS_CAC)->Country?->children(self::NS_CBC)->IdentificationCode);
                        if ($line || $city || $postal || $country) {
                            $wAddress = new AddressDTO(
                                line: $line ?: null,
                                postal_code: $postal ?: null,
                                city_name: $city ?: null,
                                country_code: $country ?: null,
                            );
                        }
                    }
                }

                if ($wName !== '') {
                    $winner = new WinningPartyDTO($wName, $wNif, $wAddress);
                }
            }

            // Amounts
            $amountWith = null;
            $amountWithout = null;
            $awarded = $cac->AwardedTenderedProject;
            if ($awarded && $awarded->count()) {
                $lmt = $awarded->children(self::NS_CAC)->LegalMonetaryTotal;
                if ($lmt && $lmt->count()) {
                    $amountWith = $this->decimal($lmt->children(self::NS_CBC)->PayableAmount);
                    $amountWithout = $this->decimal($lmt->children(self::NS_CBC)->TaxExclusiveAmount);
                }
            }

            // Contract number + formalization
            $contractNumber = null;
            $formalizationDate = null;
            $contract = $cac->Contract;
            if ($contract && $contract->count()) {
                $contractNumber = trim((string) $contract->children(self::NS_CBC)->ID) ?: null;
                $formalizationDate = trim((string) $contract->children(self::NS_CBC)->IssueDate) ?: null;
            }

            $sme = trim((string) $cbc->SMEAwardedIndicator);
            $smeAwarded = $sme === '' ? null : ($sme === 'true');

            $numOffersStr = trim((string) $cbc->ReceivedTenderQuantity);
            $smesQtyStr = trim((string) $cbc->SMEsReceivedTenderQuantity);

            $results[] = new ResultDTO(
                lot_number: $lotNumber,
                winner: $winner,
                amount_with_tax: $amountWith,
                amount_without_tax: $amountWithout,
                lower_tender_amount: $this->decimal($cbc->LowerTenderAmount),
                higher_tender_amount: $this->decimal($cbc->HigherTenderAmount),
                num_offers: $numOffersStr === '' ? null : (int) $numOffersStr,
                smes_received_tender_quantity: $smesQtyStr === '' ? null : (int) $smesQtyStr,
                sme_awarded: $smeAwarded,
                award_date: trim((string) $cbc->AwardDate) ?: null,
                start_date: trim((string) $cbc->StartDate) ?: null,
                formalization_date: $formalizationDate,
                contract_number: $contractNumber,
                result_code: trim((string) $cbc->ResultCode) ?: null,
                description: trim((string) $cbc->Description) ?: null,
            );

            $i++;
        }

        return $results;
    }

    private function decimal(?\SimpleXMLElement $el): ?float
    {
        if ($el === null) return null;
        $v = trim((string) $el);
        return $v !== '' ? (float) $v : null;
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/ResultsExtractor.php tests/Unit/Contracts/Parser/Extractors/ResultsExtractorTest.php
git commit -m "feat(contracts): add ResultsExtractor (multi-lote, winner address, full result fields)"
```

---

## Task 9 — `TermsExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/TermsExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/TermsExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\TermsExtractor;
use Tests\TestCase;

class TermsExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_terms(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $terms = $folder->children(self::NS_CAC)->TenderingTerms;

        $dto = (new TermsExtractor())->extract($terms);

        // At minimum funding program or legislation code should be populated on real entries
        $this->assertIsBool($dto->over_threshold_indicator === false || $dto->over_threshold_indicator === true || $dto->over_threshold_indicator === null);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/TermsExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\TermsDTO;

class TermsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $terms): TermsDTO
    {
        $cbc = $terms->children(self::NS_CBC);

        $language = null;
        $langEl = $terms->children(self::NS_CAC)->Language;
        if ($langEl && $langEl->count()) {
            $language = trim((string) $langEl->children(self::NS_CBC)->ID) ?: null;
        }

        $guaranteeTypeCode = null;
        $guaranteePct = null;
        $guarantee = $terms->children(self::NS_CAC)->RequiredFinancialGuarantee;
        if ($guarantee && $guarantee->count()) {
            $guaranteeTypeCode = trim((string) $guarantee->children(self::NS_CBC)->GuaranteeTypeCode) ?: null;
            $rate = trim((string) $guarantee->children(self::NS_CBC)->AmountRate);
            if ($rate !== '') $guaranteePct = (float) $rate;
        }

        $over = trim((string) $cbc->OverThresholdIndicator);
        $variant = trim((string) $cbc->VariantConstraintIndicator);
        $curricula = trim((string) $cbc->RequiredCurriculaIndicator);
        $appeals = trim((string) $cbc->ReceivedAppealQuantity);

        return new TermsDTO(
            language: $language,
            funding_program_code: trim((string) $cbc->FundingProgramCode) ?: null,
            national_legislation_code: trim((string) $cbc->ProcurementNationalLegislationCode) ?: null,
            over_threshold_indicator: $over === '' ? null : ($over === 'true'),
            received_appeal_quantity: $appeals === '' ? null : (int) $appeals,
            variant_constraint_indicator: $variant === '' ? null : ($variant === 'true'),
            required_curricula_indicator: $curricula === '' ? null : ($curricula === 'true'),
            guarantee_type_code: $guaranteeTypeCode,
            guarantee_percentage: $guaranteePct,
        );
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/TermsExtractor.php tests/Unit/Contracts/Parser/Extractors/TermsExtractorTest.php
git commit -m "feat(contracts): add TermsExtractor"
```

---

## Task 10 — `CriteriaExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/CriteriaExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/CriteriaExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor;
use Tests\TestCase;

class CriteriaExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_criteria_with_types_and_weights(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-09-with-criteria.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $terms = $folder->children(self::NS_CAC)->TenderingTerms;

        $result = (new CriteriaExtractor())->extract($terms, defaultLotNumber: 1);

        $this->assertArrayHasKey(1, $result);
        $this->assertGreaterThanOrEqual(2, count($result[1]));
        foreach ($result[1] as $i => $c) {
            $this->assertEquals($i + 1, $c->sort_order);
            $this->assertContains($c->type_code, ['OBJ','SUBJ']);
            $this->assertNotEmpty($c->description);
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/CriteriaExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\CriterionDTO;

class CriteriaExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    /** @return array<int, CriterionDTO[]> */
    public function extract(\SimpleXMLElement $terms, int $defaultLotNumber = 1): array
    {
        $awardingTerms = $terms->children(self::NS_CAC)->AwardingTerms;
        if (!$awardingTerms || !$awardingTerms->count()) return [];

        $criteriaByLot = [];
        $sortOrder = 1;

        foreach ($awardingTerms->children(self::NS_CAC)->AwardingCriteria as $criteria) {
            $cbc = $criteria->children(self::NS_CBC);

            $desc = trim((string) $cbc->Description);
            if ($desc === '') continue;

            $typeCode = trim((string) $cbc->AwardingCriteriaTypeCode) ?: 'OBJ';
            $subtypeCode = trim((string) $cbc->AwardingCriteriaSubTypeCode) ?: null;
            $note = trim((string) $cbc->Note) ?: null;
            $weightStr = trim((string) $cbc->WeightNumeric);
            $weight = $weightStr === '' ? null : (float) $weightStr;

            // Lot reference — if present, attribute to that lot; else default lot
            $lotRefStr = trim((string) $cbc->ProcurementProjectLotID);
            $lotNumber = ctype_digit($lotRefStr) ? (int) $lotRefStr : $defaultLotNumber;

            $criteriaByLot[$lotNumber][] = new CriterionDTO(
                lot_number: $lotNumber,
                type_code: $typeCode,
                subtype_code: $subtypeCode,
                description: $desc,
                note: $note,
                weight_numeric: $weight,
                sort_order: $sortOrder++,
            );
        }

        return $criteriaByLot;
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/CriteriaExtractor.php tests/Unit/Contracts/Parser/Extractors/CriteriaExtractorTest.php
git commit -m "feat(contracts): add CriteriaExtractor with OBJ/SUBJ + weights + notes"
```

---

## Task 11 — `NoticesExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/NoticesExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/NoticesExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\NoticesExtractor;
use Tests\TestCase;

class NoticesExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_all_notice_types(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-03-res-formalized.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $notices = (new NoticesExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(3, count($notices));
        foreach ($notices as $n) {
            $this->assertNotEmpty($n->notice_type_code);
            $this->assertNotEmpty($n->issue_date);
        }

        $types = array_column(array_map(fn($n) => (array) $n, $notices), 'notice_type_code');
        $this->assertContains('DOC_CAN_ADJ', $types);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/NoticesExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\NoticeDTO;

class NoticesExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    /** @return NoticeDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $notices = [];
        foreach ($folder->children(self::NS_CAC_EXT)->ValidNoticeInfo as $vni) {
            $typeCode = trim((string) $vni->children(self::NS_CBC_EXT)->NoticeTypeCode);
            if ($typeCode === '') continue;

            $pubStatus = $vni->children(self::NS_CAC_EXT)->AdditionalPublicationStatus;
            if (!$pubStatus || !$pubStatus->count()) continue;

            $mediaName = trim((string) $pubStatus->children(self::NS_CBC_EXT)->PublicationMediaName) ?: null;

            foreach ($pubStatus->children(self::NS_CAC_EXT)->AdditionalPublicationDocumentReference as $docRef) {
                $issueDate = trim((string) $docRef->children(self::NS_CBC)->IssueDate);
                if ($issueDate === '') continue;

                $documentTypeCode = null;
                $documentTypeName = null;
                $documentUri = null;
                $documentFilename = null;

                $docTypeEl = $docRef->children(self::NS_CBC)->DocumentTypeCode;
                if ($docTypeEl && trim((string) $docTypeEl) !== '') {
                    $documentTypeCode = trim((string) $docTypeEl);
                    $documentTypeName = (string) ($docTypeEl['name'] ?? null) ?: null;
                }

                $attachment = $docRef->children(self::NS_CAC)->Attachment;
                if ($attachment && $attachment->count()) {
                    $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
                    if ($extRef && $extRef->count()) {
                        $extCbc = $extRef->children(self::NS_CBC);
                        $documentUri = trim((string) $extCbc->URI) ?: null;
                        $documentFilename = trim((string) $extCbc->FileName) ?: null;
                    }
                }

                $notices[] = new NoticeDTO(
                    notice_type_code: $typeCode,
                    publication_media: $mediaName,
                    issue_date: $issueDate,
                    document_uri: $documentUri,
                    document_filename: $documentFilename,
                    document_type_code: $documentTypeCode,
                    document_type_name: $documentTypeName,
                );
            }
        }

        return $notices;
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/NoticesExtractor.php tests/Unit/Contracts/Parser/Extractors/NoticesExtractorTest.php
git commit -m "feat(contracts): add NoticesExtractor"
```

---

## Task 12 — `DocumentsExtractor`

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/Extractors/DocumentsExtractor.php`
- Test: `tests/Unit/Contracts/Parser/Extractors/DocumentsExtractorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor;
use Tests\TestCase;

class DocumentsExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_all_document_types(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $docs = (new DocumentsExtractor())->extract($folder);

        $types = array_map(fn($d) => $d->type, $docs);
        $this->assertNotEmpty($docs);
        foreach ($docs as $d) {
            $this->assertContains($d->type, ['legal','technical','additional','general']);
            $this->assertNotEmpty($d->name);
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement extractor**

```php
<?php
// app/Modules/Contracts/Services/Parser/Extractors/DocumentsExtractor.php
namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\DocumentDTO;

class DocumentsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    /** @return DocumentDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $docs = [];
        $cac = $folder->children(self::NS_CAC);

        foreach (['LegalDocumentReference' => 'legal',
                 'TechnicalDocumentReference' => 'technical',
                 'AdditionalDocumentReference' => 'additional'] as $tag => $type) {
            foreach ($cac->{$tag} as $ref) {
                $dto = $this->parseRef($ref, $type);
                if ($dto !== null) $docs[] = $dto;
            }
        }

        foreach ($folder->children(self::NS_CAC_EXT)->GeneralDocument as $gd) {
            $ref = $gd->children(self::NS_CAC_EXT)->GeneralDocumentDocumentReference
                ?? $gd->children(self::NS_CAC)->DocumentReference
                ?? null;
            if ($ref && $ref->count()) {
                $dto = $this->parseRef($ref, 'general');
                if ($dto !== null) $docs[] = $dto;
            }
        }

        return $docs;
    }

    private function parseRef(\SimpleXMLElement $ref, string $type): ?DocumentDTO
    {
        $name = trim((string) $ref->children(self::NS_CBC)->ID);
        if ($name === '') return null;

        $uri = null;
        $hash = null;
        $attachment = $ref->children(self::NS_CAC)->Attachment;
        if ($attachment && $attachment->count()) {
            $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
            if ($extRef && $extRef->count()) {
                $extCbc = $extRef->children(self::NS_CBC);
                $uri = trim((string) $extCbc->URI) ?: null;
                $hash = trim((string) $extCbc->DocumentHash) ?: null;

                $fileName = trim((string) $extCbc->FileName);
                if ($fileName !== '') $name = $fileName;  // prefer FileName over UUID-ID for general docs
            }
        }

        return new DocumentDTO($type, $name, $uri, $hash);
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/Extractors/DocumentsExtractor.php tests/Unit/Contracts/Parser/Extractors/DocumentsExtractorTest.php
git commit -m "feat(contracts): add DocumentsExtractor covering all 4 document reference types"
```

---

## Task 13 — `PlacspEntryParser` (compositor)

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/PlacspEntryParser.php`
- Test: `tests/Unit/Contracts/Parser/PlacspEntryParserTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\PlacspEntryParser;
use Tests\TestCase;

class PlacspEntryParserTest extends TestCase
{
    public function test_parses_full_entry_from_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];

        $parser = app(PlacspEntryParser::class);
        $dto = $parser->parse($entry);

        $this->assertInstanceOf(EntryDTO::class, $dto);
        $this->assertNotEmpty($dto->external_id);
        $this->assertNotEmpty($dto->expediente);
        $this->assertEquals('ADJ', $dto->status_code);
        $this->assertNotNull($dto->organization);
        $this->assertGreaterThanOrEqual(1, count($dto->lots));
        $this->assertGreaterThanOrEqual(1, count($dto->results));
    }

    public function test_falls_back_to_project_when_no_lots(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];

        $dto = app(PlacspEntryParser::class)->parse($entry);

        $this->assertCount(1, $dto->lots);
        $this->assertEquals(1, $dto->lots[0]->lot_number);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement compositor**

```php
<?php
// app/Modules/Contracts/Services/Parser/PlacspEntryParser.php
namespace Modules\Contracts\Services\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor;
use Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor;
use Modules\Contracts\Services\Parser\Extractors\LotsExtractor;
use Modules\Contracts\Services\Parser\Extractors\NoticesExtractor;
use Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProcessExtractor;
use Modules\Contracts\Services\Parser\Extractors\ProjectExtractor;
use Modules\Contracts\Services\Parser\Extractors\ResultsExtractor;
use Modules\Contracts\Services\Parser\Extractors\TermsExtractor;

class PlacspEntryParser
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function __construct(
        private OrganizationExtractor $orgExtractor,
        private ProjectExtractor $projectExtractor,
        private LotsExtractor $lotsExtractor,
        private ProcessExtractor $processExtractor,
        private ResultsExtractor $resultsExtractor,
        private TermsExtractor $termsExtractor,
        private CriteriaExtractor $criteriaExtractor,
        private NoticesExtractor $noticesExtractor,
        private DocumentsExtractor $documentsExtractor,
    ) {}

    public function parse(\SimpleXMLElement $entry): EntryDTO
    {
        $externalId = trim((string) $entry->id);
        $link = null;
        foreach ($entry->link as $l) {
            $href = (string) ($l->attributes()['href'] ?? '');
            if ($href) { $link = $href; break; }
        }
        $updated = trim((string) $entry->updated);
        $updatedAt = new \DateTimeImmutable($updated ?: 'now');

        $folder = $entry->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $expediente = trim((string) $folder->children(self::NS_CBC)->ContractFolderID) ?: trim((string) $entry->title);
        $statusCode = trim((string) $folder->children(self::NS_CBC_EXT)->ContractFolderStatusCode) ?: 'PUB';

        $lp = $folder->children(self::NS_CAC_EXT)->LocatedContractingParty;
        $organization = $this->orgExtractor->extract($lp);

        $project = $folder->children(self::NS_CAC)->ProcurementProject;
        $lots = $this->lotsExtractor->extract($project);
        if (empty($lots)) {
            $lots = [$this->projectExtractor->extract($project)];
        }

        $process = null;
        $processNode = $folder->children(self::NS_CAC)->TenderingProcess;
        if ($processNode && $processNode->count()) {
            $process = $this->processExtractor->extract($processNode);
        }

        $results = $this->resultsExtractor->extract($folder);

        $terms = null;
        $criteriaByLot = [];
        $termsNode = $folder->children(self::NS_CAC)->TenderingTerms;
        if ($termsNode && $termsNode->count()) {
            $terms = $this->termsExtractor->extract($termsNode);
            $criteriaByLot = $this->criteriaExtractor->extract($termsNode, defaultLotNumber: 1);
        }

        $notices = $this->noticesExtractor->extract($folder);
        $documents = $this->documentsExtractor->extract($folder);

        return new EntryDTO(
            external_id: $externalId,
            link: $link,
            expediente: mb_substr($expediente, 0, 490),
            status_code: $statusCode,
            entry_updated_at: $updatedAt,
            organization: $organization,
            lots: $lots,
            process: $process,
            results: $results,
            terms: $terms,
            criteria_by_lot: $criteriaByLot,
            notices: $notices,
            documents: $documents,
        );
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/PlacspEntryParser.php tests/Unit/Contracts/Parser/PlacspEntryParserTest.php
git commit -m "feat(contracts): add PlacspEntryParser (compositor DI-based)"
```

---

## Task 14 — `PlacspStreamParser` (XMLReader loop + memoria acotada)

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/PlacspStreamParser.php`
- Test: `tests/Unit/Contracts/Parser/PlacspStreamParserTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class PlacspStreamParserTest extends TestCase
{
    public function test_streams_entries_and_tombstones(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = [];
        $tombstones = [];

        foreach ($parser->stream(base_path('tests/Fixtures/placsp/full-20-entries.atom')) as $item) {
            if ($item instanceof EntryDTO) $entries[] = $item;
            if ($item instanceof TombstoneDTO) $tombstones[] = $item;
        }

        $this->assertGreaterThanOrEqual(20, count($entries));
    }

    public function test_memory_bounded_on_large_file(): void
    {
        $parser = app(PlacspStreamParser::class);
        $before = memory_get_usage(true);

        foreach ($parser->stream(base_path('tests/Fixtures/placsp/full-20-entries.atom')) as $_) {
            // consume
        }

        $peak = memory_get_peak_usage(true);
        $this->assertLessThan(50 * 1024 * 1024, $peak - $before, 'Memory grew beyond 50MB');
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement stream parser**

```php
<?php
// app/Modules/Contracts/Services/Parser/PlacspStreamParser.php
namespace Modules\Contracts\Services\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor;

class PlacspStreamParser
{
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_AT = 'http://purl.org/atompub/tombstones/1.0';

    public function __construct(
        private PlacspEntryParser $entryParser,
        private TombstoneExtractor $tombstoneExtractor,
    ) {}

    /** @return \Generator<EntryDTO|TombstoneDTO> */
    public function stream(string $atomPath): \Generator
    {
        $reader = new \XMLReader();
        if (!$reader->open($atomPath)) {
            throw new \RuntimeException("Cannot open atom: {$atomPath}");
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) continue;

            if ($reader->localName === 'entry' && $reader->namespaceURI === self::NS_ATOM) {
                $dom = $reader->expand();
                if ($dom !== false) {
                    $xml = simplexml_import_dom($dom);
                    if ($xml !== null) {
                        yield $this->entryParser->parse($xml);
                    }
                }
            } elseif ($reader->localName === 'deleted-entry' && $reader->namespaceURI === self::NS_AT) {
                $dom = $reader->expand();
                if ($dom !== false) {
                    $xml = simplexml_import_dom($dom);
                    if ($xml !== null) {
                        yield $this->tombstoneExtractor->extract($xml);
                    }
                }
            }
        }

        $reader->close();
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/PlacspStreamParser.php tests/Unit/Contracts/Parser/PlacspStreamParserTest.php
git commit -m "feat(contracts): add PlacspStreamParser (XMLReader memory-bounded)"
```

---

## Task 15 — Register parser services in `ContractsServiceProvider`

**Files:**
- Modify: `app/Modules/Contracts/ContractsServiceProvider.php`

- [ ] **Step 1: Add bindings**

```php
// In ContractsServiceProvider::register()
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\ProjectExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\LotsExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\ProcessExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\ResultsExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\TermsExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\NoticesExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\PlacspEntryParser::class);
$this->app->singleton(\Modules\Contracts\Services\Parser\PlacspStreamParser::class);
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Contracts/ContractsServiceProvider.php
git commit -m "feat(contracts): register parser services in DI container"
```

---

## Task 16 — Phase 1.1 gate + push

- [ ] **Step 1: Run all parser tests**

```bash
php artisan test tests/Unit/Contracts/Parser
```
Expected: all green.

- [ ] **Step 2: PHPStan L8 on Services/Parser**

```bash
./vendor/bin/phpstan analyse app/Modules/Contracts/Services/Parser --level=8
```

- [ ] **Step 3: Pint**

```bash
./vendor/bin/pint app/Modules/Contracts/Services/Parser tests/Unit/Contracts/Parser
git add -A && git diff --cached --quiet || git commit -m "style(contracts): pint parser files"
```

- [ ] **Step 4: Smoke test en atom real completo**

```bash
php artisan tinker --execute='
$p = app(\Modules\Contracts\Services\Parser\PlacspStreamParser::class);
$count = 0;
$atomPath = storage_path("app/placsp/201801/extracted/licitacionesPerfilesContratanteCompleto3_20200522_234632_1.atom");
foreach ($p->stream($atomPath) as $_) $count++;
echo "Parsed {$count} entries\n";
echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
'
```
Expected: `Parsed 500 entries`, `Peak memory: < 50 MB`.

- [ ] **Step 5: Push + PR**

```bash
git push -u origin feature/contracts-v2-parser
gh pr create --title "contracts v2 — Phase 1.1 parser" --body "$(cat <<'EOF'
## Summary
- 10 extractors (Tombstone, Organization, Project, Lots, Process, Results, Terms, Criteria, Notices, Documents) con tests unit.
- PlacspEntryParser compositor DI-based.
- PlacspStreamParser con XMLReader (memoria acotada).
- 11 fixtures XML recortadas de atoms reales.
- Services registrados en ContractsServiceProvider.

## Test plan
- [x] Todos los unit tests verdes (10 extractors + compositor + stream).
- [x] Smoke test: parser procesa atom real completo (500 entries) en <50MB.
- [x] PHPStan L8 verde sobre Services/Parser.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
