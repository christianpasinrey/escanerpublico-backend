<?php

namespace Tests\Feature\Tax\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del endpoint POST /api/v1/tax/fractional-payment (M8).
 *
 * Cubre golden cases, validación 422, estructura del payload, disclaimer
 * legal y compatibilidad modelo/régimen.
 */
class FractionalPaymentApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'model' => '130',
            'regime' => 'EDN',
            'year' => 2025,
            'quarter' => 1,
            'cumulative_gross_revenue' => '12000.00',
            'cumulative_deductible_expenses' => '3000.00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload131(array $overrides = []): array
    {
        return array_merge([
            'model' => '131',
            'regime' => 'EO',
            'year' => 2025,
            'quarter' => 4,
            'activity_code' => '721.2',
            'eo_modules_data' => [
                'personal_no_asalariado' => 1,
                'distancia_km' => 80,
            ],
            'salaried_employees' => 0,
        ], $overrides);
    }

    public function test_endpoint_modelo_130_devuelve_breakdown_completo(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload());

        $r->assertSuccessful();
        $r->assertJsonStructure([
            'data' => [
                'breakdown' => ['lines', 'summary', 'net_result', 'currency', 'meta'],
                'model',
                'period',
                'totals' => [
                    'cumulative_net_income',
                    'applicable_rate',
                    'gross_payment',
                    'deduction_descendants',
                    'withholdings_applied',
                    'previous_quarters_deducted',
                    'result',
                ],
                'is_to_pay',
                'is_clamped_to_zero',
                'disclaimer',
            ],
        ]);

        $r->assertJsonPath('data.model', '130');
        $r->assertJsonPath('data.period', '1T 2025');
        $r->assertJsonPath('data.totals.gross_payment.amount', '1800.00');
        $r->assertJsonPath('data.totals.result.amount', '1800.00');
        $r->assertJsonPath('data.is_to_pay', true);
    }

    public function test_endpoint_modelo_131_taxi_q4_2025_sin_asalariados(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload131());

        $r->assertSuccessful();
        $r->assertJsonPath('data.model', '131');
        $r->assertJsonPath('data.period', '4T 2025');
        $r->assertJsonPath('data.totals.applicable_rate.percentage', '4.00');
        $r->assertJsonPath('data.totals.gross_payment.amount', '228.05');
        $r->assertJsonPath('data.totals.result.amount', '228.05');
    }

    public function test_endpoint_disclaimer_referencia_legislacion(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload());
        $r->assertSuccessful();

        $disclaimer = $r->json('data.disclaimer');
        $this->assertIsString($disclaimer);
        $this->assertStringContainsString('LIRPF', $disclaimer);
        $this->assertStringContainsString('RIRPF', $disclaimer);
        $this->assertStringContainsString('informativo', strtolower($disclaimer));
    }

    public function test_endpoint_modelo_130_con_eo_devuelve_422(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'regime' => 'EO',
        ]));

        $r->assertStatus(422);
        $body = $r->json();
        $this->assertStringContainsString('no es compatible', strtolower($body['message']));
    }

    public function test_endpoint_modelo_131_con_edn_devuelve_422(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload131([
            'regime' => 'EDN',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_quarter_fuera_de_rango_devuelve_422(): void
    {
        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'quarter' => 5,
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_quarter_cero_devuelve_422(): void
    {
        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'quarter' => 0,
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_modelo_invalido_devuelve_422(): void
    {
        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'model' => '202',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_year_fuera_de_rango_devuelve_422(): void
    {
        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'year' => 1999,
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_pagos_previos_mayores_que_bruto_truncado_a_cero(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'previous_quarters_payments' => '5000.00',
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.result.amount', '0.00');
        $r->assertJsonPath('data.is_clamped_to_zero', true);
        $r->assertJsonPath('data.is_to_pay', false);
    }

    public function test_endpoint_descendientes_aplicados_q3_2025(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'quarter' => 3,
            'cumulative_gross_revenue' => '30000.00',
            'cumulative_deductible_expenses' => '8000.00',
            'withholdings_applied' => '3000.00',
            'previous_quarters_payments' => '2500.00',
            'taxpayer_situation' => [
                'descendants' => 2,
                'descendants_under_3' => 0,
            ],
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.gross_payment.amount', '4400.00');
        $r->assertJsonPath('data.totals.deduction_descendants.amount', '200.00');
        // 4400 − 200 − 3000 − 2500 = −1300 → 0,00 €.
        $r->assertJsonPath('data.totals.result.amount', '0.00');
        $r->assertJsonPath('data.is_clamped_to_zero', true);
    }

    public function test_endpoint_response_no_se_cachea(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload());

        $r->assertSuccessful();
        $cacheControl = $r->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $cacheControl);
    }

    public function test_endpoint_modelo_131_actividad_no_soportada_devuelve_422(): void
    {
        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload131([
            'activity_code' => '999.9',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_eds_aplica_reduccion_generica(): void
    {
        $this->seedTaxParameters(2025);

        $r = $this->postJson('/api/v1/tax/fractional-payment', $this->basePayload([
            'regime' => 'EDS',
            'quarter' => 2,
            'cumulative_gross_revenue' => '24000.00',
            'cumulative_deductible_expenses' => '6000.00',
            'previous_quarters_payments' => '1800.00',
        ]));

        $r->assertSuccessful();
        // Previo 18.000; 7 % red = 1.260; neto 16.740; bruto 3.348; − 1.800 = 1.548.
        $r->assertJsonPath('data.totals.cumulative_net_income.amount', '16740.00');
        $r->assertJsonPath('data.totals.gross_payment.amount', '3348.00');
        $r->assertJsonPath('data.totals.result.amount', '1548.00');
    }
}
