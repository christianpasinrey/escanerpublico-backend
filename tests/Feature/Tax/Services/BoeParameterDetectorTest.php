<?php

namespace Tests\Feature\Tax\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Legislation\Models\LegislationNorm;
use Modules\Tax\Models\TaxParameterAlert;
use Modules\Tax\Services\BoeParameterDetector;
use Tests\TestCase;

class BoeParameterDetectorTest extends TestCase
{
    use RefreshDatabase;

    private BoeParameterDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = $this->app->make(BoeParameterDetector::class);
    }

    public function test_detects_irpf_reglamento(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto 142/2024 que modifica el Reglamento del Impuesto sobre la Renta de las Personas Físicas',
        ]);

        $created = $this->detector->scan();

        $this->assertGreaterThanOrEqual(1, $created);
        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'irpf_reglamento',
            'status' => TaxParameterAlert::STATUS_PENDING,
        ]);
    }

    public function test_detects_pge_law(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Ley 31/2022, de 23 de diciembre, de Presupuestos Generales del Estado para el año 2023',
        ]);

        $this->detector->scan();

        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'pge_ley',
        ]);
    }

    public function test_detects_ss_cotizacion(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto sobre cotización a la Seguridad Social, desempleo y otros',
        ]);

        $this->detector->scan();

        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'ss_cotizacion',
        ]);
    }

    public function test_detects_iva_modification(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto-ley 4/2024 de medidas urgentes en materia de IVA y otros tributos',
        ]);

        $this->detector->scan();

        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'iva_tipos',
        ]);
    }

    public function test_detects_autonomo_rdley(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto-ley 13/2022 sobre nuevo sistema de cotización autónomos RETA',
        ]);

        $this->detector->scan();

        // El título matchea tanto autonomo_tramos como ss_cotizacion → 2 alertas.
        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'autonomo_tramos',
        ]);
    }

    public function test_detects_mei(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto-ley 8/2023 que actualiza el Mecanismo de Equidad Intergeneracional',
        ]);

        $this->detector->scan();

        $this->assertDatabaseHas('tax_parameter_alerts', [
            'source_legislation_norm_id' => $norm->id,
            'matched_pattern' => 'mei',
        ]);
    }

    public function test_does_not_match_unrelated_norms(): void
    {
        LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto 100/2024 sobre fomento del cine español y cuotas de pantalla',
        ]);
        LegislationNorm::factory()->create([
            'titulo' => 'Resolución de la Dirección General de Tráfico sobre puntos del carnet',
        ]);

        $this->detector->scan();

        $this->assertSame(0, TaxParameterAlert::count());
    }

    public function test_is_idempotent(): void
    {
        LegislationNorm::factory()->create([
            'titulo' => 'Ley de Presupuestos Generales del Estado para 2026',
        ]);

        $first = $this->detector->scan();
        $second = $this->detector->scan();

        $this->assertGreaterThanOrEqual(1, $first);
        $this->assertSame(0, $second);

        // No duplicados
        $count = TaxParameterAlert::where('matched_pattern', 'pge_ley')->count();
        $this->assertSame(1, $count);
    }

    public function test_skips_norms_without_titulo(): void
    {
        LegislationNorm::factory()->create([
            'titulo' => null,
        ]);
        LegislationNorm::factory()->create([
            'titulo' => '',
        ]);

        $created = $this->detector->scan();

        $this->assertSame(0, $created);
        $this->assertSame(0, TaxParameterAlert::count());
    }

    public function test_scan_single_returns_correct_count(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Real Decreto-ley sobre cotización Seguridad Social autónomos RETA',
        ]);

        $created = $this->detector->scanSingle($norm);

        // Matchea ss_cotizacion + autonomo_tramos
        $this->assertSame(2, $created);
    }

    public function test_status_counts_aggregates_by_status(): void
    {
        $norm = LegislationNorm::factory()->create([
            'titulo' => 'Ley de Presupuestos Generales del Estado para 2026',
        ]);
        $this->detector->scan();

        TaxParameterAlert::query()->update(['status' => 'reviewed']);
        TaxParameterAlert::create([
            'source_legislation_norm_id' => $norm->id,
            'suggested_action' => 'manual',
            'status' => 'pending',
            'matched_pattern' => 'manual_test',
        ]);

        $counts = $this->detector->statusCounts();
        $this->assertArrayHasKey('reviewed', $counts);
        $this->assertArrayHasKey('pending', $counts);
    }
}
