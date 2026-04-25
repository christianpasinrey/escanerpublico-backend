<?php

namespace Tests\Feature\Tax\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests del endpoint POST /api/v1/tax/vat-return (M7).
 *
 * Cubre golden cases, validación 422, estructura del payload, casillas
 * modelo 303 expuestas y disclaimer legal.
 */
class VatReturnApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'regime' => 'IVA_GEN',
            'year' => 2025,
            'quarter' => 2,
            'transactions' => [
                [
                    'direction' => 'outgoing',
                    'date' => '2025-04-15',
                    'base' => '1000.00',
                    'vat_rate' => '21.00',
                    'vat_amount' => '210.00',
                    'description' => 'Factura emitida cliente A',
                    'category' => 'domestic',
                ],
            ],
        ], $overrides);
    }

    public function test_endpoint_returns_full_breakdown_for_simple_input(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload());

        $r->assertSuccessful();
        $r->assertJsonStructure([
            'data' => [
                'breakdown' => ['lines', 'summary', 'net_result', 'currency', 'meta'],
                'model',
                'period',
                'totals' => [
                    'total_vat_accrued',
                    'total_vat_deductible',
                    'total_surcharge_equivalence_accrued',
                    'liquid_quota',
                ],
                'result',
                'result_label',
                'casillas',
                'disclaimer',
            ],
        ]);

        $r->assertJsonPath('data.totals.total_vat_accrued.amount', '210.00');
        $r->assertJsonPath('data.totals.liquid_quota.amount', '210.00');
        $r->assertJsonPath('data.result', 'a_ingresar');
        $r->assertJsonPath('data.model', '303');
        $r->assertJsonPath('data.period', '2T 2025');
    }

    public function test_endpoint_includes_disclaimer_with_legislation_refs(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload());
        $r->assertSuccessful();

        $disclaimer = $r->json('data.disclaimer');
        $this->assertIsString($disclaimer);
        $this->assertStringContainsString('informativo', strtolower($disclaimer));
        $this->assertStringContainsString('LIVA', $disclaimer);
        $this->assertStringContainsString('303', $disclaimer);
    }

    public function test_endpoint_exposes_casillas_modelo_303(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload());

        $r->assertSuccessful();
        $casillas = $r->json('data.casillas');

        // Casillas se exponen como array de {key, value} para preservar
        // el orden y evitar normalización de claves numéricas.
        $this->assertIsArray($casillas);
        $this->assertNotEmpty($casillas);

        $byKey = [];
        foreach ($casillas as $entry) {
            $byKey[$entry['key']] = $entry['value'];
        }

        $this->assertArrayHasKey('01', $byKey);
        $this->assertArrayHasKey('03', $byKey);
        $this->assertArrayHasKey('27', $byKey);
        $this->assertSame('1000.00', $byKey['01']['amount']);
        $this->assertSame('210.00', $byKey['03']['amount']);
        $this->assertSame('210.00', $byKey['27']['amount']);
    }

    public function test_endpoint_returns_422_for_regime_outside_mvp(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'regime' => 'IVA_REBU',
        ]));

        $r->assertStatus(422);
        $body = $r->json();
        $this->assertStringContainsString('MVP', $body['message']);
    }

    public function test_endpoint_returns_422_for_oss_regime(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'regime' => 'IVA_OSS',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_unknown_regime(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'regime' => 'NOT_A_REGIME',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_invalid_quarter(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'quarter' => 5,
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_iva_caja_without_paid_date(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'regime' => 'IVA_CAJA',
        ]));

        $r->assertStatus(422);
        $body = $r->json();
        $this->assertStringContainsString('Caja', $body['message']);
    }

    public function test_endpoint_accepts_iva_caja_with_paid_date(): void
    {
        $payload = $this->basePayload();
        $payload['regime'] = 'IVA_CAJA';
        $payload['transactions'][0]['paid_date'] = '2025-05-10';

        $r = $this->postJson('/api/v1/tax/vat-return', $payload);

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_vat_accrued.amount', '210.00');
    }

    public function test_endpoint_returns_422_for_vat_rate_outside_catalog(): void
    {
        $payload = $this->basePayload();
        $payload['transactions'][0]['vat_rate'] = '13.50'; // tipo no estándar
        $payload['transactions'][0]['vat_amount'] = '135.00';

        $r = $this->postJson('/api/v1/tax/vat-return', $payload);

        $r->assertStatus(422);
        $body = $r->json();
        $this->assertStringContainsString('vat_product_rates', $body['message']);
    }

    public function test_endpoint_returns_422_for_inconsistent_vat_amount(): void
    {
        $payload = $this->basePayload();
        $payload['transactions'][0]['vat_amount'] = '50.00'; // 1000 × 21% = 210, no 50

        $r = $this->postJson('/api/v1/tax/vat-return', $payload);

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_empty_transactions_iva_gen(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'transactions' => [],
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_iva_simple_without_modules_data(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'regime' => 'IVA_SIMPLE',
            'transactions' => [],
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_invalid_year(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'year' => 1999,
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_invalid_direction(): void
    {
        $payload = $this->basePayload();
        $payload['transactions'][0]['direction'] = 'sideways';

        $r = $this->postJson('/api/v1/tax/vat-return', $payload);

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_negative_carry_forward(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload([
            'previous_quota_carry_forward' => '-100.00',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_iva_simple_with_modules_data(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', [
            'regime' => 'IVA_SIMPLE',
            'year' => 2025,
            'quarter' => 1,
            'transactions' => [],
            'simplified_modules_data' => [
                'modules' => [
                    ['concept' => 'Personal asalariado', 'units' => '1', 'annual_quota_per_unit' => '1200.00'],
                ],
                'period_fraction' => '0.25',
            ],
        ]);

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_vat_accrued.amount', '300.00');
    }

    public function test_endpoint_response_is_no_store_cache(): void
    {
        $r = $this->postJson('/api/v1/tax/vat-return', $this->basePayload());

        $r->assertSuccessful();
        $cacheControl = $r->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $cacheControl);
    }

    public function test_endpoint_returns_a_compensar_for_intermediate_period_negative(): void
    {
        $payload = $this->basePayload();
        $payload['transactions'] = [
            [
                'direction' => 'outgoing',
                'date' => '2025-02-15',
                'base' => '100.00',
                'vat_rate' => '21.00',
                'vat_amount' => '21.00',
                'description' => 'Venta pequeña',
            ],
            [
                'direction' => 'incoming',
                'date' => '2025-03-15',
                'base' => '500.00',
                'vat_rate' => '21.00',
                'vat_amount' => '105.00',
                'description' => 'Compra grande',
            ],
        ];
        $payload['quarter'] = 1;

        $r = $this->postJson('/api/v1/tax/vat-return', $payload);

        $r->assertSuccessful();
        $r->assertJsonPath('data.result', 'a_compensar');

        $casillas = $r->json('data.casillas');
        $byKey = [];
        foreach ($casillas as $entry) {
            $byKey[$entry['key']] = $entry['value'];
        }
        $this->assertArrayHasKey('72', $byKey);
        $this->assertSame('84.00', $byKey['72']['amount']);
    }
}
