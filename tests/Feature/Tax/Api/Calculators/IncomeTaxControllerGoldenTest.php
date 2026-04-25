<?php

namespace Tests\Feature\Tax\Api\Calculators;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Golden tests del endpoint POST /api/v1/tax/income-tax (modelo 100 IRPF).
 *
 * Cada caso documenta sus inputs, valores esperados y la fuente legal
 * (cita textual al BOE — Ley 35/2006, Ley 31/2022, Ley 1/2024 CAM, etc.)
 * y se calcula a mano paso a paso.
 *
 * IMPORTANTE: bcmath trunca a 2 decimales en cada operación intermedia. Los
 * importes esperados están alineados con esta realidad (ver Money.php).
 */
class IncomeTaxControllerGoldenTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
    }

    /**
     * CASO 1 — Asalariado 30k Madrid 2025 sin hijos.
     *
     *  Bruto 30.000, SS 1.944,99 → previous net 28.055,01
     *  Reducción art. 20 LIRPF: 0 (>19.747,50)
     *  Mínimo personal: 5.550
     *  Cuota estatal: 2.763,75
     *  Cuota MD: 2.398,50
     *  Cuota total: 5.162,25
     *  Sin retenciones → resultado 5.162,25 (a ingresar)
     *
     * Coincide con caso M4 PayrollCalculator anualizado.
     */
    public function test_caso_1_asalariado_30k_madrid_2025(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => [
                'married' => false,
                'descendants' => 0,
            ],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertSuccessful();

        $totals = $response->json('data.totals');
        $this->assertSame('28055.01', $totals['net_income']['amount']);
        $this->assertSame('2763.75', $totals['state_quota']['amount']);
        $this->assertSame('2398.50', $totals['regional_quota']['amount']);
        $this->assertSame('5162.25', $totals['total_gross']['amount']);
        $this->assertSame('5162.25', $totals['liquid_quota']['amount']);
        $this->assertSame('5162.25', $totals['result']['amount']);
        $this->assertTrue($response->json('data.is_to_pay'));
    }

    /**
     * CASO 2 — Autónomo profesional EDN Madrid 2025: 50k ingresos / 15k gastos.
     *
     *  Rendimiento neto: 35.000 (sin reducción 5/7 % en EDN)
     *  Mínimo personal: 5.550
     *  Cuota estatal: 3.805,50
     *  Cuota MD: 3.287,46
     *  Cuota total: 7.092,96
     *  Pagos fraccionados: 4.000
     *  Resultado: 3.092,96 (a ingresar)
     *
     * Fuente: Ley 35/2006 art. 28-29.
     */
    public function test_caso_2_autonomo_edn_50k_madrid_2025(): void
    {
        $payload = [
            'regime' => 'EDN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => [
                'descendants' => 0,
            ],
            'economic_activity' => [
                'activity_code' => '849.7',
                'gross_revenue' => 50000,
                'deductible_expenses' => 15000,
                'quarterly_payments_already_paid' => 4000,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertSuccessful();

        $totals = $response->json('data.totals');
        $this->assertSame('35000.00', $totals['net_income']['amount']);
        $this->assertSame('7092.96', $totals['total_gross']['amount']);
        $this->assertSame('3092.96', $totals['result']['amount']);
    }

    /**
     * CASO 3 — Autónomo EDS Cataluña 2025: 40k ingresos / 8k gastos.
     *
     *  Previous net: 32.000 → Reducción 7 % = 2.240 → cap 2.000.
     *  Rendimiento neto: 30.000.
     *  Mínimo personal: 5.550.
     *  Cuota estatal: 3.055,50
     *  Cuota CT: 3.166,38
     *  Cuota total: 6.221,88
     *  Pagos fraccionados: 3.000
     *  Resultado: 3.221,88 (a ingresar)
     *
     * Fuente: art. 30 RIRPF + Ley 31/2022 (subida 7 %) + Ley 5/2020 DOGC.
     */
    public function test_caso_3_autonomo_eds_40k_cataluna_2025(): void
    {
        $payload = [
            'regime' => 'EDS',
            'year' => 2025,
            'region' => 'CT',
            'taxpayer_situation' => [
                'descendants' => 0,
            ],
            'economic_activity' => [
                'activity_code' => '843.5',
                'gross_revenue' => 40000,
                'deductible_expenses' => 8000,
                'quarterly_payments_already_paid' => 3000,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertSuccessful();

        $totals = $response->json('data.totals');
        $this->assertSame('30000.00', $totals['net_income']['amount']);
        $this->assertSame('6221.88', $totals['total_gross']['amount']);
        $this->assertSame('3221.88', $totals['result']['amount']);
    }

    /**
     * CASO 4 — Taxi (epígrafe IAE 721.2) Andalucía 2025 según Anexo II Orden HFP.
     *
     *  Módulos: 1 titular + 30k km
     *  Rendimiento previo: 4.798,40
     *  Reducción 5 % DT 32ª: 239,92
     *  Rendimiento neto: 4.558,48
     *  Mínimo personal: 5.550 (mayor que la base → cuota cero)
     *  Resultado: 0 − 200 (pagos 131) = −200 (a devolver)
     *
     * Fuente: Anexo II Orden HFP/1397/2024 (BOE-A-2024-26896).
     */
    public function test_caso_4_taxi_eo_andalucia_2025(): void
    {
        $payload = [
            'regime' => 'EO',
            'year' => 2025,
            'region' => 'AN',
            'taxpayer_situation' => [
                'descendants' => 0,
            ],
            'economic_activity' => [
                'activity_code' => '721.2',
                'gross_revenue' => 40000,
                'deductible_expenses' => 0,
                'quarterly_payments_already_paid' => 200,
                'eo_modules_data' => [
                    'personal_no_asalariado' => 1,
                    'distancia_km' => 30,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertSuccessful();

        $totals = $response->json('data.totals');
        $this->assertSame('4558.48', $totals['net_income']['amount']);
        $this->assertSame('0.00', $totals['liquid_quota']['amount']);
        $this->assertSame('-200.00', $totals['result']['amount']);
        $this->assertTrue($response->json('data.is_to_refund'));
    }

    /**
     * CASO 5 — Casado con 2 hijos, asalariado 60k Valencia 2025.
     *
     *  Bruto 60.000, SS 3.819,55 (capada por base máx) → previo 56.180,45
     *  Reducción art. 20 LIRPF: 0
     *  Rendimiento neto: 56.180,45
     *  Mínimo personal+familiar: 5.550 + 2.400 + 2.700 = 10.650
     *
     *  Cuota estatal: 7.232,38
     *  Cuota VC: 7.512,10
     *  Cuota total: 14.744,48
     *  Sin deducciones (sólo 2 hijos, no fam. numerosa).
     *  Pagos a cuenta: 12.000
     *  Resultado: 2.744,48 (a ingresar)
     *
     * Fuente: Ley 35/2006 art. 56-67 + Ley 9/2022 GVA escala VC.
     */
    public function test_caso_5_casado_60k_valencia_2025_2_hijos(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'VC',
            'taxpayer_situation' => [
                'married' => true,
                'spouse_has_income' => true,
                'descendants' => 2,
            ],
            'work_income' => [
                'gross' => 60000,
                'social_security_paid' => 3819.55,
                'irpf_withheld' => 12000,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertSuccessful();

        $totals = $response->json('data.totals');
        $this->assertSame('56180.45', $totals['net_income']['amount']);
        $this->assertSame('14744.48', $totals['total_gross']['amount']);
        $this->assertSame('2744.48', $totals['result']['amount']);
    }

    public function test_disclaimer_is_prominent(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertSuccessful();

        $disclaimer = $response->json('data.disclaimer');
        $this->assertNotNull($disclaimer);
        $this->assertStringContainsString('informativa', $disclaimer);
        $this->assertStringContainsString('asesor', $disclaimer);
        // Debe mencionar las exclusiones MVP.
        $this->assertStringContainsString('Beckham', $disclaimer);
    }

    public function test_breakdown_lines_have_legal_references(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertSuccessful();

        $lines = $response->json('data.breakdown.lines');
        $linesWithRef = collect($lines)->filter(fn ($l) => ! empty($l['legal_reference']));
        $this->assertGreaterThan(5, $linesWithRef->count());

        foreach ($linesWithRef as $line) {
            $this->assertStringStartsWith('https://', $line['legal_reference']);
        }
    }

    public function test_response_marks_no_store(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    // ============== EDGE CASES ==============

    public function test_edge_unsupported_regime_beckham_returns_422(): void
    {
        $payload = [
            'regime' => 'BECKHAM',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['regime']);
    }

    public function test_edge_year_below_min_returns_422(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2022,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year']);
    }

    public function test_edge_year_above_max_returns_422(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2027,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['year']);
    }

    public function test_edge_unsupported_region_returns_422(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'GA',
            'taxpayer_situation' => ['descendants' => 0],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['region']);
    }

    public function test_edge_asalariado_without_work_income_returns_422(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
    }

    public function test_edge_edn_without_economic_activity_returns_422(): void
    {
        $payload = [
            'regime' => 'EDN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
    }

    public function test_edge_eo_with_unsupported_activity_code_returns_422(): void
    {
        $payload = [
            'regime' => 'EO',
            'year' => 2025,
            'region' => 'AN',
            'taxpayer_situation' => ['descendants' => 0],
            'economic_activity' => [
                'activity_code' => '999.999',
                'gross_revenue' => 30000,
                'deductible_expenses' => 0,
                'quarterly_payments_already_paid' => 0,
                'eo_modules_data' => ['personal_no_asalariado' => 1],
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('cubierto en MVP', (string) $response->json('message'));
    }

    public function test_edge_eo_with_deductible_expenses_returns_422(): void
    {
        $payload = [
            'regime' => 'EO',
            'year' => 2025,
            'region' => 'AN',
            'taxpayer_situation' => ['descendants' => 0],
            'economic_activity' => [
                'activity_code' => '721.2',
                'gross_revenue' => 30000,
                'deductible_expenses' => 5000, // ← inválido en EO
                'quarterly_payments_already_paid' => 0,
                'eo_modules_data' => ['personal_no_asalariado' => 1],
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
    }

    /**
     * EDGE — Pagos fraccionados superiores a la cuota líquida → resultado a devolver.
     */
    public function test_edge_quarterly_payments_above_liquid_quota_returns_refund(): void
    {
        $payload = [
            'regime' => 'EDN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => ['descendants' => 0],
            'economic_activity' => [
                'activity_code' => '849.7',
                'gross_revenue' => 30000,
                'deductible_expenses' => 5000,
                'quarterly_payments_already_paid' => 10000, // mucho más que la cuota líquida
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertSuccessful();

        $this->assertTrue($response->json('data.is_to_refund'));
        $resultAmount = (float) $response->json('data.totals.result.amount');
        $this->assertLessThan(0.0, $resultAmount);
    }

    public function test_edge_descendants_under_3_greater_than_descendants_returns_422(): void
    {
        $payload = [
            'regime' => 'ASALARIADO_GEN',
            'year' => 2025,
            'region' => 'MD',
            'taxpayer_situation' => [
                'descendants' => 1,
                'descendants_under_3' => 2,
            ],
            'work_income' => [
                'gross' => 30000,
                'social_security_paid' => 1944.99,
                'irpf_withheld' => 0,
            ],
        ];

        $response = $this->postJson('/api/v1/tax/income-tax', $payload);
        $response->assertStatus(422);
    }
}
