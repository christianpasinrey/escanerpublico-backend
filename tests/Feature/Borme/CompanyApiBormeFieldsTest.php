<?php

namespace Tests\Feature\Borme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Company;
use Tests\TestCase;

class CompanyApiBormeFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_borme_fields_in_resource(): void
    {
        Company::create([
            'name' => 'ORIGEN Y SENTIDO SL',
            'name_normalized' => 'origen y sentido',
            'legal_form' => 'SL',
            'registry_letter' => 'M',
            'registry_sheet' => 882492,
            'capital_cents' => 300000,
            'capital_currency' => 'EUR',
            'domicile_address' => 'C/ ORENSE, 20',
            'domicile_city' => 'MADRID',
            'incorporation_date' => '2026-03-03',
            'last_act_date' => '2026-04-06',
            'status' => 'active',
            'source_modules' => ['borme'],
        ]);

        $response = $this->getJson('/api/v1/companies');

        $response->assertOk()->assertJsonPath('data.0.legal_form', 'SL');
        $response->assertJsonPath('data.0.status', 'active');
        $response->assertJsonPath('data.0.registry.letter', 'M');
        $response->assertJsonPath('data.0.registry.sheet', 882492);
        $response->assertJsonPath('data.0.capital.amount_cents', 300000);
        $response->assertJsonPath('data.0.domicile.city', 'MADRID');
        $response->assertJsonPath('data.0.incorporation_date', '2026-03-03');
        $response->assertJsonPath('data.0.last_act_date', '2026-04-06');
        $response->assertJsonPath('data.0.source_modules', ['borme']);
    }

    public function test_filter_by_source_module(): void
    {
        Company::create(['name' => 'BORME ONLY', 'source_modules' => ['borme']]);
        Company::create(['name' => 'PLACSP ONLY', 'source_modules' => ['placsp']]);
        Company::create(['name' => 'BOTH', 'source_modules' => ['borme', 'placsp']]);
        Company::create(['name' => 'NONE', 'source_modules' => null]);

        $response = $this->getJson('/api/v1/companies?filter[source]=borme');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['BORME ONLY', 'BOTH'], $names);
    }

    public function test_filter_by_status_and_legal_form(): void
    {
        Company::create(['name' => 'ALIVE SL', 'legal_form' => 'SL', 'status' => 'active']);
        Company::create(['name' => 'DEAD SA', 'legal_form' => 'SA', 'status' => 'extinct']);
        Company::create(['name' => 'ALIVE SA', 'legal_form' => 'SA', 'status' => 'active']);

        $response = $this->getJson('/api/v1/companies?filter[status]=extinct');
        $this->assertSame(['DEAD SA'], collect($response->json('data'))->pluck('name')->all());

        $response = $this->getJson('/api/v1/companies?filter[legal_form]=SL');
        $this->assertSame(['ALIVE SL'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_index_lists_companies_without_awards(): void
    {
        // BORME-only company (no contracts at all): must still appear in listing.
        Company::create(['name' => 'NO CONTRACTS', 'source_modules' => ['borme']]);

        $response = $this->getJson('/api/v1/companies');
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('NO CONTRACTS', $names);
    }
}
