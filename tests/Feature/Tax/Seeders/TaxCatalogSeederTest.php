<?php

namespace Tests\Feature\Tax\Seeders;

use Database\Seeders\Tax\TaxCatalogSeeder;
use Database\Seeders\Tax\TaxStateFeesSeeder;
use Database\Seeders\Tax\TaxStateTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;
use Tests\TestCase;

class TaxCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_taxes_seeder_loads_at_least_17_state_impuestos(): void
    {
        $this->seed(TaxStateTypesSeeder::class);

        $count = TaxType::query()
            ->where('scope', Scope::State->value)
            ->where('levy_type', LevyType::Impuesto->value)
            ->whereNull('region_code')
            ->count();

        $this->assertGreaterThanOrEqual(17, $count);
    }

    public function test_state_fees_seeder_loads_at_least_13_tasas(): void
    {
        $this->seed(TaxStateFeesSeeder::class);

        $count = TaxType::query()
            ->where('scope', Scope::State->value)
            ->where('levy_type', LevyType::Tasa->value)
            ->whereNull('region_code')
            ->count();

        $this->assertGreaterThanOrEqual(13, $count);
    }

    public function test_full_catalog_is_idempotent(): void
    {
        $this->seed(TaxCatalogSeeder::class);
        $first = TaxType::count();

        // Re-ejecutar no debería duplicar.
        $this->seed(TaxCatalogSeeder::class);
        $second = TaxType::count();

        $this->assertSame($first, $second);
    }

    public function test_full_catalog_meets_minimum_coverage(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $this->assertGreaterThanOrEqual(
            25,
            TaxType::count(),
            'M2 requiere al menos 25 tax_types poblados',
        );

        $this->assertGreaterThanOrEqual(
            50,
            TaxRate::count(),
            'M2 requiere al menos 50 tax_rates poblados',
        );
    }

    public function test_regional_seeder_creates_entries_for_top4_ccaa(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        foreach (['MD', 'CT', 'AN', 'VC'] as $region) {
            $count = TaxType::where('scope', Scope::Regional->value)
                ->where('region_code', $region)
                ->count();

            $this->assertGreaterThanOrEqual(
                5,
                $count,
                "La CCAA {$region} debe tener al menos 5 tributos cargados",
            );
        }
    }

    public function test_dni_fee_has_12_eur_fixed_amount(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $type = TaxType::where('code', 'TASA_DNI')->first();
        $this->assertNotNull($type);

        $rate = TaxRate::where('tax_type_id', $type->id)
            ->where('year', 2025)
            ->first();

        $this->assertNotNull($rate);
        $this->assertSame('12.00', $rate->fixed_amount);
        $this->assertNull($rate->rate);
    }

    public function test_iva_general_2025_is_21_percent(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $type = TaxType::where('code', 'IVA')
            ->where('scope', Scope::State->value)
            ->whereNull('region_code')
            ->first();

        $this->assertNotNull($type);

        $rate = TaxRate::where('tax_type_id', $type->id)
            ->where('year', 2025)
            ->where('rate', 21.0000)
            ->first();

        $this->assertNotNull($rate);
        $this->assertSame('21.0000', $rate->rate);
    }

    public function test_is_general_2025_and_2026_is_25_percent(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $type = TaxType::where('code', 'IS')->whereNull('region_code')->first();
        $this->assertNotNull($type);

        foreach ([2025, 2026] as $year) {
            $rate = TaxRate::where('tax_type_id', $type->id)
                ->where('year', $year)
                ->where('rate', 25.0000)
                ->first();
            $this->assertNotNull($rate, "IS general 25% año {$year} debe existir");
        }
    }

    public function test_madrid_isd_bonification_is_99_percent(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $type = TaxType::where('code', 'ISD')
            ->where('scope', Scope::Regional->value)
            ->where('region_code', 'MD')
            ->first();

        $this->assertNotNull($type);

        $rate = TaxRate::where('tax_type_id', $type->id)
            ->where('year', 2025)
            ->first();
        $this->assertNotNull($rate);
        $this->assertSame('99.0000', $rate->rate);
    }

    public function test_all_tax_types_have_base_law_url(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $sinUrl = TaxType::whereNull('base_law_url')->count();
        $this->assertSame(0, $sinUrl, 'Todos los tax_types deben citar base_law_url al BOE');
    }

    public function test_all_tax_types_have_editorial_md(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $sinEditorial = TaxType::whereNull('editorial_md')->count();
        $this->assertSame(0, $sinEditorial, 'Todos los tax_types deben tener editorial_md');
    }

    public function test_state_fees_tasas_in_top_set_are_present(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $expected = [
            'TASA_DNI',
            'TASA_PASAPORTE',
            'TASA_NIE',
            'TASA_JUDICIAL_PF',
            'TASA_JUDICIAL_PJ',
            'TASA_TITULO_UNIV_OFICIAL',
            'TASA_TITULO_UNIV_PROPIO',
            'TASA_PORTUARIA_BUQUE',
            'TASA_AEROPORTUARIA',
            'TASA_CNMV_INSCRIPCION',
            'TASA_BOE_PUBLICACION',
            'TASA_OEPM_PATENTE',
            'TASA_OEPM_MARCA',
        ];

        foreach ($expected as $code) {
            $exists = TaxType::where('code', $code)
                ->where('levy_type', LevyType::Tasa->value)
                ->exists();
            $this->assertTrue($exists, "Falta la tasa estatal {$code}");
        }
    }
}
