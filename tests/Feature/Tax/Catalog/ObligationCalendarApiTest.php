<?php

namespace Tests\Feature\Tax\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeObligation;
use Tests\TestCase;

class ObligationCalendarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_returns_quarterly_dates_for_eds(): void
    {
        $regime = TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegimeObligation::query()->create([
            'regime_id' => $regime->id,
            'model_code' => '130',
            'periodicity' => 'quarterly',
            'description' => 'Pago fraccionado',
        ]);
        TaxRegimeObligation::query()->create([
            'regime_id' => $regime->id,
            'model_code' => '100',
            'periodicity' => 'annual',
            'description' => 'Renta',
        ]);

        $r = $this->getJson('/api/v1/tax/calendar?regime=EDS&year=2025');
        $r->assertSuccessful();

        $r->assertJsonPath('data.year', 2025);
        $r->assertJsonPath('data.regime.code', 'EDS');

        $entries = $r->json('data.entries');
        $this->assertCount(5, $entries); // 4 trimestres + 1 anual
        // Verificar que hay un evento en abril 2025
        $aprilDates = array_filter($entries, fn ($e) => str_starts_with($e['date'], '2025-04'));
        $this->assertNotEmpty($aprilDates);

        // Verificar fecha 100 anual (renta de 2025 → 30 junio 2026)
        $rentDates = array_filter($entries, fn ($e) => $e['model_code'] === '100');
        $this->assertCount(1, $rentDates);
        $this->assertSame('2026-06-30', array_values($rentDates)[0]['date']);
    }

    public function test_calendar_orders_chronologically(): void
    {
        $regime = TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        TaxRegimeObligation::query()->create([
            'regime_id' => $regime->id,
            'model_code' => '130',
            'periodicity' => 'quarterly',
        ]);

        $r = $this->getJson('/api/v1/tax/calendar?regime=EDS&year=2025');
        $entries = $r->json('data.entries');

        $previous = null;
        foreach ($entries as $entry) {
            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $entry['date']);
            }
            $previous = $entry['date'];
        }
    }

    public function test_returns_404_for_unknown_regime(): void
    {
        $r = $this->getJson('/api/v1/tax/calendar?regime=NOPE&year=2025');
        $r->assertStatus(404);
        $r->assertJsonPath('code', 'REGIME_NOT_FOUND');
    }

    public function test_returns_422_when_regime_param_missing(): void
    {
        $r = $this->getJson('/api/v1/tax/calendar?year=2025');
        $r->assertStatus(422);
        $r->assertJsonPath('code', 'REGIME_REQUIRED');
    }

    public function test_returns_422_when_year_out_of_range(): void
    {
        TaxRegime::query()->create(['code' => 'EDS', 'scope' => 'irpf', 'name' => 'EDS']);
        $r = $this->getJson('/api/v1/tax/calendar?regime=EDS&year=1500');
        $r->assertStatus(422);
        $r->assertJsonPath('code', 'YEAR_OUT_OF_RANGE');
    }

    public function test_calendar_handles_is_fractional_three_dates(): void
    {
        $regime = TaxRegime::query()->create(['code' => 'IS_GEN', 'scope' => 'is', 'name' => 'IS General']);
        TaxRegimeObligation::query()->create([
            'regime_id' => $regime->id,
            'model_code' => '202',
            'periodicity' => 'quarterly',
        ]);

        $r = $this->getJson('/api/v1/tax/calendar?regime=IS_GEN&year=2025');
        $r->assertSuccessful();
        $entries = $r->json('data.entries');
        $this->assertCount(3, $entries);
        // Abril, octubre, diciembre
        $months = array_map(fn ($e) => substr($e['date'], 5, 2), $entries);
        $this->assertSame(['04', '10', '12'], $months);
    }
}
