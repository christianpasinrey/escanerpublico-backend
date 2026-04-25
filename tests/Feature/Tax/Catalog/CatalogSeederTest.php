<?php

namespace Tests\Feature\Tax\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\database\seeders\TaxCatalogSeeder;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeCompatibility;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_28_known_regimes(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $expected = count(RegimeCode::all());
        $this->assertSame($expected, TaxRegime::query()->count(), 'Cada código de RegimeCode debe estar sembrado.');

        // Spot-check
        $eds = TaxRegime::query()->where('code', 'EDS')->first();
        $this->assertNotNull($eds);
        $this->assertSame('irpf', $eds->scope);
        $this->assertNotNull($eds->editorial_md);
        $this->assertNotNull($eds->legal_reference_url);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(TaxCatalogSeeder::class);
        $first = TaxRegime::query()->count();

        // Re-run does not duplicate
        $this->seed(TaxCatalogSeeder::class);
        $second = TaxRegime::query()->count();

        $this->assertSame($first, $second);
    }

    public function test_seeder_creates_compatibilities(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $this->assertGreaterThan(15, TaxRegimeCompatibility::query()->count());
        // Verify EDS↔EO are exclusive
        $eds = TaxRegime::query()->where('code', 'EDS')->first();
        $eo = TaxRegime::query()->where('code', 'EO')->first();
        $this->assertDatabaseHas('tax_regime_compatibility', [
            'regime_a_id' => $eds->id,
            'regime_b_id' => $eo->id,
            'compatibility' => 'exclusive',
        ]);
    }

    public function test_seeder_creates_obligations_with_models(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $eds = TaxRegime::query()->where('code', 'EDS')->first();

        $this->assertSame(2, $eds->obligations()->count(), 'EDS debe tener modelo 130 trimestral + 100 anual');
        $this->assertTrue($eds->obligations()->where('model_code', '130')->exists());
        $this->assertTrue($eds->obligations()->where('model_code', '100')->exists());

        // IS GEN: 202 + 200
        $isGen = TaxRegime::query()->where('code', 'IS_GEN')->first();
        $this->assertSame(2, $isGen->obligations()->count());
    }

    public function test_seeder_imports_economic_activities(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $cnaeCount = EconomicActivity::query()->where('system', 'cnae')->count();
        $iaeCount = EconomicActivity::query()->where('system', 'iae')->count();

        // Esperamos algo razonable del fixture committeado
        $this->assertGreaterThan(80, $cnaeCount, 'CNAE: al menos 80 actividades del fixture base');
        $this->assertGreaterThan(50, $iaeCount, 'IAE: al menos 50 epígrafes del fixture base');
    }

    public function test_seeder_creates_activity_regime_mappings(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $this->assertGreaterThan(50, ActivityRegimeMapping::query()->count(), 'Debe haber > 50 mappings actividad ↔ régimen');

        // Hostelería CNAE 5510 → IVA reducido 10 %
        $hotels = EconomicActivity::query()
            ->where('system', 'cnae')
            ->where('code', '5510')
            ->first();

        if ($hotels !== null) {
            $mapping = ActivityRegimeMapping::query()->where('activity_id', $hotels->id)->first();
            $this->assertNotNull($mapping, 'CNAE 5510 (hoteles) debe tener mapping');
            $this->assertSame(10, $mapping->vat_rate_default);
        }

        // CNAE 86210 (medicina general) → exento (vat = 0) y retención 7 %
        $medicina = EconomicActivity::query()
            ->where('system', 'cnae')
            ->where('code', '86210')
            ->first();

        if ($medicina !== null) {
            $mapping = ActivityRegimeMapping::query()->where('activity_id', $medicina->id)->first();
            $this->assertNotNull($mapping);
            $this->assertSame(0, $mapping->vat_rate_default);
        }
    }

    public function test_seeded_endpoint_returns_full_catalog(): void
    {
        $this->seed(TaxCatalogSeeder::class);

        $r = $this->getJson('/api/v1/tax/regimes?per_page=100');
        $r->assertSuccessful();
        $r->assertJsonCount(count(RegimeCode::all()), 'data');
    }
}
