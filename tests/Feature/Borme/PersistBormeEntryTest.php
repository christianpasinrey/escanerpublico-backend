<?php

namespace Tests\Feature\Borme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Borme\Models\BormeActItem;
use Modules\Borme\Models\BormeEntry;
use Modules\Borme\Models\BormeOfficer;
use Modules\Borme\Models\BormePdf;
use Modules\Borme\Models\Person;
use Modules\Borme\Services\Parsers\SectionOneParser;
use Modules\Borme\Services\PersistBormeEntry;
use Modules\Contracts\Models\Company;
use Tests\TestCase;

class PersistBormeEntryTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE_PDF = 'tests/Fixtures/Borme/pdfs/BORME-A-2026-78-28.pdf';

    private function pdf(): BormePdf
    {
        return BormePdf::create([
            'date' => '2026-04-24',
            'bulletin_no' => 78,
            'cve' => 'BORME-A-2026-78-28',
            'section' => 'A',
            'province_ine' => '28',
            'province_name' => 'MADRID',
            'source_url' => 'https://www.boe.es/borme/dias/2026/04/24/pdfs/BORME-A-2026-78-28.pdf',
            'status' => 'downloaded',
        ]);
    }

    private function entryDto(int $entryNumber)
    {
        $parser = $this->app->make(SectionOneParser::class);
        foreach ($parser->parseFile(base_path(self::FIXTURE_PDF)) as $entry) {
            if ($entry->entryNumber === $entryNumber) {
                return $entry;
            }
        }
        $this->fail("Entry {$entryNumber} not found");
    }

    public function test_constitution_creates_company_with_capital_and_domicile(): void
    {
        $action = $this->app->make(PersistBormeEntry::class);
        $pdf = $this->pdf();

        $entry = $action->persist($pdf, $this->entryDto(199800));

        $company = $entry->company;
        $this->assertSame('ORIGEN Y SENTIDO SL', $company->name);
        $this->assertSame('origen y sentido', $company->name_normalized);
        $this->assertSame('SL', $company->legal_form);
        $this->assertSame('M', $company->registry_letter);
        $this->assertSame(882492, $company->registry_sheet);
        $this->assertSame(300000, $company->capital_cents);
        $this->assertSame('EUR', $company->capital_currency);
        $this->assertSame('MADRID', $company->domicile_city);
        $this->assertSame('active', $company->status);
        $this->assertSame('2026-03-03', $company->incorporation_date->toDateString());
        $this->assertSame('2026-04-06', $company->last_act_date->toDateString());
        $this->assertContains('borme', $company->source_modules);

        $this->assertCount(2, $entry->officers);
        $actTypes = $entry->actItems->pluck('act_type')->all();
        $this->assertContains('constitution', $actTypes);
        $this->assertContains('appointment', $actTypes);
    }

    public function test_dissolution_extinction_marks_company_extinct(): void
    {
        $action = $this->app->make(PersistBormeEntry::class);
        $pdf = $this->pdf();

        $entry = $action->persist($pdf, $this->entryDto(199801));

        $this->assertSame('extinct', $entry->company->status);
        $this->assertCount(2, $entry->officers, 'expected 1 cease + 1 appointment officer');
    }

    public function test_company_as_officer_creates_separate_company_row(): void
    {
        $action = $this->app->make(PersistBormeEntry::class);
        $pdf = $this->pdf();

        $entry = $action->persist($pdf, $this->entryDto(200428));

        $this->assertSame('VERTICE MADRID OBRAS SL', $entry->company->name);

        $officer = $entry->officers->first();
        $this->assertSame('company', $officer->officer_kind);
        $this->assertNotNull($officer->officer_company_id);
        $this->assertNotNull($officer->representative_person_id);
        $this->assertNull($officer->officer_person_id);

        $magnafox = Company::find($officer->officer_company_id);
        $this->assertSame('MAGNAFOX GROUND SOLID SL', $magnafox->name);
        $this->assertSame('magnafox ground solid', $magnafox->name_normalized);

        $vilar = Person::find($officer->representative_person_id);
        $this->assertSame('VILAR MARTINEZ JOAN VICENT', $vilar->full_name_raw);
    }

    public function test_re_persisting_same_entry_is_idempotent(): void
    {
        $action = $this->app->make(PersistBormeEntry::class);
        $pdf = $this->pdf();
        $dto = $this->entryDto(199800);

        $action->persist($pdf, $dto);
        $action->persist($pdf, $dto);

        $this->assertSame(1, BormeEntry::where('entry_number', 199800)->count());
        $this->assertSame(2, BormeOfficer::count(), 'officers replaced, not duplicated');
        $this->assertSame(2, BormeActItem::count(), 'act_items (constitution+appointment) replaced, not duplicated');
        $this->assertSame(1, Company::where('name', 'ORIGEN Y SENTIDO SL')->count());
    }

    public function test_resolver_links_existing_company_by_registry_sheet(): void
    {
        $existing = Company::create([
            'name' => 'ORIGEN Y SENTIDO SL',
            'name_normalized' => 'origen y sentido',
            'registry_letter' => 'M',
            'registry_sheet' => 882492,
            'source_modules' => ['placsp'],
        ]);

        $action = $this->app->make(PersistBormeEntry::class);
        $entry = $action->persist($this->pdf(), $this->entryDto(199800));

        $this->assertSame($existing->id, $entry->company_id, 'should reuse the existing company row');
        $this->assertSame('matched', $entry->resolution_status);

        $existing->refresh();
        $this->assertEqualsCanonicalizing(['placsp', 'borme'], $existing->source_modules);
    }
}
