<?php

namespace Tests\Feature\Tax\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests del endpoint POST /api/v1/tax/invoice (M5).
 *
 * Cubre golden cases, validación 422 y estructura del payload.
 */
class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'lines' => [
                [
                    'description' => 'Servicio',
                    'quantity' => '1',
                    'unit_price' => '1000.00',
                    'vat_rate_type' => 'general',
                    'irpf_retention_applies' => true,
                ],
            ],
            'issuer_nif' => '00000000T',
            'issuer_vat_regime' => 'IVA_GEN',
            'issuer_irpf_regime' => 'EDN',
            'client_type' => 'empresa',
            'client_country' => 'ES',
            'issue_date' => '2025-06-15',
        ], $overrides);
    }

    public function test_endpoint_returns_full_breakdown_for_simple_invoice(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload());

        $r->assertSuccessful();
        $r->assertJsonStructure([
            'data' => [
                'breakdown' => ['lines', 'summary', 'net_result', 'currency', 'meta'],
                'lines',
                'totals' => [
                    'subtotal',
                    'total_vat',
                    'total_surcharge_equivalence',
                    'total_irpf_retention',
                    'total_to_charge',
                ],
                'disclaimer',
            ],
        ]);

        $r->assertJsonPath('data.totals.subtotal.amount', '1000.00');
        $r->assertJsonPath('data.totals.total_vat.amount', '210.00');
        $r->assertJsonPath('data.totals.total_irpf_retention.amount', '150.00');
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1060.00');
    }

    public function test_endpoint_includes_disclaimer_legal(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload());
        $r->assertSuccessful();

        $disclaimer = $r->json('data.disclaimer');
        $this->assertIsString($disclaimer);
        $this->assertStringContainsString('informativo', strtolower($disclaimer));
    }

    public function test_endpoint_returns_422_for_invalid_nif(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_nif' => 'NOTANIF1',
        ]));

        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_unknown_vat_rate_type(): void
    {
        $payload = $this->basePayload();
        $payload['lines'][0]['vat_rate_type'] = 'invalid_type';

        $r = $this->postJson('/api/v1/tax/invoice', $payload);
        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_empty_lines(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'lines' => [],
        ]));
        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_unknown_regime(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_vat_regime' => 'IVA_INEXISTENTE',
        ]));
        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_iva_regime_outside_mvp(): void
    {
        // IVA_REBU es código conocido (RegimeCode lo acepta) pero NO está
        // soportado en M5 — debe responder 422 con mensaje claro.
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_vat_regime' => 'IVA_REBU',
        ]));
        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_negative_unit_price(): void
    {
        $payload = $this->basePayload();
        $payload['lines'][0]['unit_price'] = '-10.00';
        $r = $this->postJson('/api/v1/tax/invoice', $payload);
        $r->assertStatus(422);
    }

    public function test_endpoint_returns_422_for_zero_quantity(): void
    {
        $payload = $this->basePayload();
        $payload['lines'][0]['quantity'] = '0';
        $r = $this->postJson('/api/v1/tax/invoice', $payload);
        $r->assertStatus(422);
    }

    public function test_endpoint_handles_new_activity_flag(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_new_activity_flag' => true,
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_irpf_retention.amount', '70.00');
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1140.00');
    }

    public function test_endpoint_handles_particular_client(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'client_type' => 'particular',
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_irpf_retention.amount', '0.00');
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1210.00');
    }

    public function test_endpoint_handles_recargo_equivalencia_client(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'surcharge_equivalence_flag' => true,
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_surcharge_equivalence.amount', '52.00');
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1112.00');
    }

    public function test_endpoint_handles_intracommunity_client(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'client_country' => 'FR',
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_vat.amount', '0.00');
        $r->assertJsonPath('data.totals.total_irpf_retention.amount', '0.00');
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1000.00');
    }

    public function test_endpoint_handles_simplificado_regime(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_vat_regime' => 'IVA_SIMPLE',
            'issuer_irpf_regime' => 'EO',
        ]));

        $r->assertSuccessful();
        // En módulos no hay retención por defecto sin actividad concreta.
        $r->assertJsonPath('data.totals.total_irpf_retention.amount', '0.00');
        $r->assertJsonPath('data.totals.total_vat.amount', '210.00');

        // Debe haber una línea INFO sobre el régimen simplificado.
        $lines = $r->json('data.breakdown.lines');
        $hasSimplifiedNote = false;
        foreach ($lines as $line) {
            if (isset($line['concept']) && stripos($line['concept'], 'simplificado') !== false) {
                $hasSimplifiedNote = true;
                break;
            }
        }
        $this->assertTrue($hasSimplifiedNote, 'Falta nota sobre régimen simplificado.');
    }

    public function test_endpoint_handles_recargo_equivalencia_regime(): void
    {
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload([
            'issuer_vat_regime' => 'IVA_RE',
            'client_type' => 'particular',
        ]));

        $r->assertSuccessful();
        $r->assertJsonPath('data.totals.total_to_charge.amount', '1210.00');

        // Debe haber una línea INFO sobre RE.
        $lines = $r->json('data.breakdown.lines');
        $hasReNote = false;
        foreach ($lines as $line) {
            if (isset($line['concept']) && stripos($line['concept'], 'Recargo de Equivalencia') !== false) {
                $hasReNote = true;
                break;
            }
        }
        $this->assertTrue($hasReNote, 'Falta nota sobre Recargo de Equivalencia.');
    }

    public function test_endpoint_response_has_no_store_cache_header(): void
    {
        // Privacy: las respuestas con datos calculados a partir del NIF no
        // deben cachearse en proxies/CDN.
        $r = $this->postJson('/api/v1/tax/invoice', $this->basePayload());
        $r->assertSuccessful();
        $cacheControl = $r->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
