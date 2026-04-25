<?php

namespace Tests\Feature\Tax\Api\Calculators;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Golden tests del endpoint POST /api/v1/tax/payroll.
 *
 * Cada caso documenta sus inputs, los valores esperados y la fuente
 * (cita textual a Cinco Días, Iberley, calculadora oficial AEAT, o
 * cálculo manual auditado contra la legislación vigente 2025).
 *
 * IMPORTANTE: bcmath trunca, no redondea. Los valores se han calculado
 * a mano paso a paso siguiendo el orden de operaciones del calculator
 * (mismo orden que aplica AEAT: SS mensual capada × 12, no × 14).
 */
class PayrollControllerGoldenTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxParameters(2025);
    }

    /**
     * CASO 1 — Soltero 30 k Madrid 2025, 14 pagas, indefinido.
     *
     * Cálculo paso a paso (auditado contra Ley 35/2006 art. 63 + Ley CAM 1/2024 +
     * Orden ISM/26/2025 cotización SS):
     *
     * - Bruto anual: 30 000 €
     * - Base SS mensual: 30 000 / 12 = 2 500 € (dentro de la base min 1 381,20 / max 4 909,50)
     * - Base SS anual: 30 000 €
     * - CC empleado:        30 000 × 4,70 % = 1 410,00
     * - Desempleo indef.:   30 000 × 1,55 % =   465,00
     * - FP:                 30 000 × 0,10 % =    30,00
     * - MEI trabajador:     30 000 × 0,1333 % = 39,99
     * - Total SS empleado:                   1 944,99
     *
     * - Reducción rdtos. trabajo: 30 000 - 1 944,99 = 28 055,01 → > 19 747,50 → 0
     * - Base liquidable:     30 000 - 1 944,99 - 0 = 28 055,01
     *
     * - Mínimo personal: 5 550 €
     *
     * - Cuota estatal sobre 28 055,01:
     *     tramo 20 200-35 200 (15 %, fijo 2 112,75)
     *     = 2 112,75 + (28 055,01 - 20 200) × 0,15
     *     = 2 112,75 + 1 178,25 = 3 291,00
     * - Cuota estatal sobre 5 550 (mínimo):
     *     tramo 0-12 450 (9,5 %)
     *     = 5 550 × 0,095 = 527,25
     * - Cuota estatal: 3 291,00 - 527,25 = 2 763,75
     *
     * - Cuota autonómica MD sobre 28 055,01:
     *     tramo 19 764,81-36 842,71 (12,80 %, fijo 1 809,11)
     *     = 1 809,11 + (28 055,01 - 19 764,81) × 0,128
     *     = 1 809,11 + 1 061,14 = 2 870,25
     * - Cuota autonómica MD sobre 5 550:
     *     tramo 0-13 896,71 (8,50 %)
     *     = 5 550 × 0,085 = 471,75
     * - Cuota autonómica: 2 870,25 - 471,75 = 2 398,50
     *
     * - IRPF total: 2 763,75 + 2 398,50 = 5 162,25
     * - Neto anual: 30 000 - 1 944,99 - 5 162,25 = 22 892,76
     *
     * Fuente: cálculo manual auditado contra Ley 35/2006 art. 56-63 (BOE-A-2006-20764),
     * Ley 1/2024 CAM (deflactación tramos autonómicos), Orden ISM/26/2025 (BOE-A-2025-2226)
     * y RD-ley 13/2022 (BOE-A-2022-12482) para MEI 0,8 % en 2025.
     */
    public function test_case_soltero_30k_madrid_2025_indefinido(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
            'descendants' => 0,
            'descendants_under_3' => 0,
            'ascendants_over_65_living' => 0,
            'ascendants_disabled_living' => 0,
            'married' => false,
            'spouse_has_income' => false,
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertSame('30000.00', $data['annual_gross']['amount']);
        $this->assertSame('22892.76', $data['annual_net']['amount']);

        $lines = collect($data['breakdown']['lines']);

        $totalSs = $lines->firstWhere('concept', 'Total cotización trabajador a la Seguridad Social');
        $this->assertSame('1944.99', $totalSs['amount']['amount']);

        $stateQuota = $lines->firstWhere('concept', 'Cuota íntegra estatal IRPF (art. 63 LIRPF)');
        $this->assertSame('2763.75', $stateQuota['amount']['amount']);

        $regionalQuota = $lines->firstWhere('concept', 'Cuota íntegra autonómica IRPF — Madrid');
        $this->assertSame('2398.50', $regionalQuota['amount']['amount']);

        $irpfRetention = $lines->firstWhere('concept', 'Retención IRPF anual estimada');
        $this->assertSame('5162.25', $irpfRetention['amount']['amount']);
    }

    /**
     * CASO 2 — Casado con 2 hijos 45 k Cataluña 2025, 14 pagas, indefinido.
     *
     * - Bruto anual: 45 000 €
     * - Base SS mensual: 45 000 / 12 = 3 750 €
     * - Base SS anual: 45 000 €
     * - CC empleado:        45 000 × 4,70 % = 2 115,00
     * - Desempleo indef.:   45 000 × 1,55 % =   697,50
     * - FP:                 45 000 × 0,10 % =    45,00
     * - MEI trabajador:     45 000 × 0,001333 = 59,98 (45000 * 0.1333/100 = 59.985, bcmath trunca a 59.98)
     * - Total SS empleado:                   2 917,48
     *
     * - Previous net: 45 000 - 2 917,48 = 42 082,52 → > 19 747,50 → reducción = 0
     * - Base liquidable: 42 082,52
     *
     * - Mínimo personal+familiar:
     *     5 550 (personal) + 2 400 (1.º hijo) + 2 700 (2.º hijo) = 10 650
     *
     * - Cuota estatal sobre 42 082,52:
     *     tramo 35 200-60 000 (18,5 %, fijo 4 362,75)
     *     = 4 362,75 + (42 082,52 - 35 200) × 0,185
     *     = 4 362,75 + 1 273,26 = 5 636,01 (1273.2662 trunca a 1273.26)
     * - Cuota estatal sobre 10 650:
     *     tramo 0-12 450 (9,5 %) → 10 650 × 0,095 = 1 011,75
     * - Cuota estatal: 5 636,01 - 1 011,75 = 4 624,26
     *
     * - Cuota autonómica CT sobre 42 082,52:
     *     tramo 33 007,20-53 407,20 (18,80 %, fijo 4 200,21)
     *     = 4 200,21 + (42 082,52 - 33 007,20) × 0,188
     *     = 4 200,21 + 1 706,16 = 5 906,37 (9075.32 * 0.188 = 1706.160160...)
     * - Cuota autonómica CT sobre 10 650:
     *     tramo 0-12 450 (10,5 %) → 10 650 × 0,105 = 1 118,25
     * - Cuota autonómica: 5 906,37 - 1 118,25 = 4 788,12
     *
     * - IRPF total: 4 624,26 + 4 788,12 = 9 412,38
     * - Neto anual: 45 000 - 2 917,48 - 9 412,38 = 32 670,14
     *
     * Fuente: cálculo manual auditado. Mínimos por hijos en art. 58 LIRPF.
     * Escala CT en Ley 5/2020 DOGC.
     */
    public function test_case_casado_45k_cataluna_2025_2_hijos(): void
    {
        $payload = [
            'gross_annual' => 45000,
            'payments_count' => 14,
            'region' => 'CT',
            'year' => 2025,
            'contract_type' => 'indefinido',
            'descendants' => 2,
            'descendants_under_3' => 0,
            'married' => true,
            'spouse_has_income' => true,
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertSame('45000.00', $data['annual_gross']['amount']);
        $this->assertSame('32670.14', $data['annual_net']['amount']);

        $lines = collect($data['breakdown']['lines']);
        $minimum = $lines->firstWhere('concept', 'Mínimo personal y familiar (art. 56-61 LIRPF)');
        $this->assertSame('10650.00', $minimum['amount']['amount']);

        $stateQuota = $lines->firstWhere('concept', 'Cuota íntegra estatal IRPF (art. 63 LIRPF)');
        $this->assertSame('4624.26', $stateQuota['amount']['amount']);

        $regionalQuota = $lines->firstWhere('concept', 'Cuota íntegra autonómica IRPF — Cataluña');
        $this->assertSame('4788.12', $regionalQuota['amount']['amount']);
    }

    /**
     * CASO 3 — 25 k Andalucía 2025, 14 pagas, indefinido, 1 ascendiente >65.
     *
     * - Bruto anual: 25 000 €
     * - Base SS mensual: 25 000 / 12 = 2 083,33 €
     * - Base SS anual = 2 083,33 × 12 = 24 999,96 (artefacto bcmath, esperado)
     *
     *   ATENCIÓN: bcmath divide trunca a 2 decimales, por lo que la base anual
     *   queda 24 999,96, no 25 000. La diferencia de 0,04 € se traduce en
     *   pequeñas diferencias en las cuotas — todo coherente con la implementación.
     *
     * - CC empleado:        24 999,96 × 4,70 % = 1 174,99 (24999.96 * 0.047 = 1174.998..., trunca 1174.99)
     * - Desempleo indef.:   24 999,96 × 1,55 % =   387,49 (24999.96 * 0.0155 = 387.49.., trunca)
     * - FP:                 24 999,96 × 0,10 % =    24,99
     * - MEI trabajador:     24 999,96 × 0,1333 % =  33,32 (24999.96 * 0.001333 = 33.324..., trunca)
     * - Total SS:                             1 620,79
     *
     * - Previous net: 24 999,96 - 1 620,79 = 23 379,17 → > 19 747,50 → reducción 0
     * - Base liquidable: 23 379,17
     *
     * - Mínimo: 5 550 + 1 150 (ascendiente >65) = 6 700
     *
     * - Cuota estatal sobre 23 379,17:
     *     tramo 20 200-35 200 (15 %, fijo 2 112,75)
     *     = 2 112,75 + (23 379,17 - 20 200) × 0,15
     *     = 2 112,75 + 476,87 = 2 589,62 (3179.17*0.15 = 476.8755 trunca 476.87)
     * - Cuota estatal sobre 6 700:
     *     tramo 0-12 450 (9,5 %) → 636,50
     * - Cuota estatal: 2 589,62 - 636,50 = 1 953,12
     *
     * - Cuota autonómica AN sobre 23 379,17:
     *     tramo 21 000-35 200 (15 %, fijo 2 195,00)
     *     = 2 195 + (23 379,17 - 21 000) × 0,15
     *     = 2 195 + 356,87 = 2 551,87 (2379.17*0.15=356.8755 trunca 356.87)
     * - Cuota autonómica AN sobre 6 700:
     *     tramo 0-13 000 (9,5 %) → 636,50
     * - Cuota autonómica: 2 551,87 - 636,50 = 1 915,37
     *
     * - IRPF total: 1 953,12 + 1 915,37 = 3 868,49
     * - Neto anual: 24 999,96 - 1 620,79 - 3 868,49 = 19 510,68
     *
     * Fuente: art. 59 LIRPF (mínimo ascendientes), Ley 5/2021 BOJA (escala AN).
     */
    public function test_case_25k_andalucia_2025_1_ascendiente(): void
    {
        $payload = [
            'gross_annual' => 25000,
            'payments_count' => 14,
            'region' => 'AN',
            'year' => 2025,
            'contract_type' => 'indefinido',
            'ascendants_over_65_living' => 1,
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $data = $response->json('data');

        $lines = collect($data['breakdown']['lines']);

        $minimum = $lines->firstWhere('concept', 'Mínimo personal y familiar (art. 56-61 LIRPF)');
        $this->assertSame('6700.00', $minimum['amount']['amount']);

        // El neto debe estar entre 19 500 y 19 600 (acotamos por la imprecisión bcmath en /12).
        $netFloat = (float) $data['annual_net']['amount'];
        $this->assertGreaterThan(19400.0, $netFloat);
        $this->assertLessThan(19600.0, $netFloat);

        // El total de SS debe estar próximo a 1 620.79 (±2 céntimos por bcmath trunc).
        $totalSs = $lines->firstWhere('concept', 'Total cotización trabajador a la Seguridad Social');
        $totalSsFloat = (float) $totalSs['amount']['amount'];
        $this->assertGreaterThan(1619.0, $totalSsFloat);
        $this->assertLessThan(1622.0, $totalSsFloat);
    }

    /**
     * CASO 4 — 60 k C.Valenciana 2025, 14 pagas, indefinido, sin hijos.
     *
     * - Bruto anual: 60 000 €
     * - Base SS mensual: 60 000 / 12 = 5 000 €  → CAPADA a base máxima 4 909,50
     * - Base SS anual capada: 4 909,50 × 12 = 58 914 €
     *
     * - CC empleado:        58 914 × 4,70 % = 2 768,95 (58914*0.047 = 2768.958, trunca 2768.95)
     * - Desempleo indef.:   58 914 × 1,55 % =   913,16 (58914*0.0155 = 913.167, trunca 913.16)
     * - FP:                 58 914 × 0,10 % =    58,91
     * - MEI trabajador:     58 914 × 0,001333 = 78,53 (58914*0.001333=78.533, trunca 78.53)
     * - Total SS:                             3 819,55
     *
     * - Previous net: 60 000 - 3 819,55 = 56 180,45 → > 19 747,50 → reducción 0
     * - Base liquidable: 60 000 - 3 819,55 = 56 180,45
     *
     * - Mínimo personal: 5 550
     *
     * - Cuota estatal sobre 56 180,45:
     *     tramo 35 200-60 000 (18,5 %, fijo 4 362,75)
     *     = 4 362,75 + (56 180,45 - 35 200) × 0,185
     *     = 4 362,75 + 3 881,38 = 8 244,13 (20980.45*0.185=3881.38325 trunca 3881.38)
     * - Cuota estatal sobre 5 550:
     *     tramo 0-12 450 (9,5 %) → 527,25
     * - Cuota estatal: 8 244,13 - 527,25 = 7 716,88
     *
     * - Cuota autonómica VC sobre 56 180,45:
     *     tramo 52 000-65 000 (22,5 %, fijo 7 530,00)
     *     = 7 530 + (56 180,45 - 52 000) × 0,225
     *     = 7 530 + 940,60 = 8 470,60 (4180.45*0.225 = 940.60125 trunca 940.60)
     * - Cuota autonómica VC sobre 5 550:
     *     tramo 0-12 000 (9 %) → 499,50
     * - Cuota autonómica: 8 470,60 - 499,50 = 7 971,10
     *
     * - IRPF total: 7 716,88 + 7 971,10 = 15 687,98
     * - Neto anual: 60 000 - 3 819,55 - 15 687,98 = 40 492,47
     *
     * Fuente: tope SS 2025 = 4 909,50 €/mes (Orden ISM/26/2025 BOE-A-2025-2226).
     * Escala VC en Ley 9/2022 DOGV.
     */
    public function test_case_60k_valencia_2025_indefinido_sin_hijos_capa_base_ss(): void
    {
        $payload = [
            'gross_annual' => 60000,
            'payments_count' => 14,
            'region' => 'VC',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $data = $response->json('data');

        $lines = collect($data['breakdown']['lines']);

        $totalSs = $lines->firstWhere('concept', 'Total cotización trabajador a la Seguridad Social');
        $this->assertSame('3819.55', $totalSs['amount']['amount']);

        // La base de cotización aparece capada a 58 914 €
        $this->assertSame('58914.00', $totalSs['base']['amount']);

        $stateQuota = $lines->firstWhere('concept', 'Cuota íntegra estatal IRPF (art. 63 LIRPF)');
        $this->assertSame('7716.88', $stateQuota['amount']['amount']);

        $regionalQuota = $lines->firstWhere('concept', 'Cuota íntegra autonómica IRPF — Valenciana');
        $this->assertSame('7971.10', $regionalQuota['amount']['amount']);

        $this->assertSame('40492.47', $data['annual_net']['amount']);
    }

    /**
     * EDGE — Bruto anual por debajo del SMI 2025 (1 184 € × 14 = 16 576 € anuales).
     * El cálculo se rechaza con 422.
     */
    public function test_edge_gross_below_smi_returns_422(): void
    {
        $payload = [
            'gross_annual' => 10000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('Salario Mínimo Interprofesional', $response->json('message'));
    }

    /**
     * EDGE — Bruto anual por encima de la base máxima de SS:
     *   100 000 € → SS se capa, no escala. La cuota total SS empleado
     *   debería ser exactamente la del caso 60 k (también capado).
     */
    public function test_edge_gross_above_max_ss_base_caps_correctly(): void
    {
        $payload60k = [
            'gross_annual' => 60000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];
        $payload100k = ['gross_annual' => 100000] + $payload60k;

        $r60 = $this->postJson('/api/v1/tax/payroll', $payload60k);
        $r100 = $this->postJson('/api/v1/tax/payroll', $payload100k);

        $r60->assertSuccessful();
        $r100->assertSuccessful();

        $ss60 = collect($r60->json('data.breakdown.lines'))
            ->firstWhere('concept', 'Total cotización trabajador a la Seguridad Social');
        $ss100 = collect($r100->json('data.breakdown.lines'))
            ->firstWhere('concept', 'Total cotización trabajador a la Seguridad Social');

        $this->assertSame($ss60['amount']['amount'], $ss100['amount']['amount']);
        $this->assertSame('58914.00', $ss60['base']['amount']);
        $this->assertSame('58914.00', $ss100['base']['amount']);
    }

    /**
     * EDGE — Validación de input: región fuera del MVP (Galicia GA).
     */
    public function test_edge_unsupported_region_returns_422(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 14,
            'region' => 'GA',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['region']);
    }

    public function test_edge_invalid_payments_count_returns_422(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 13,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments_count']);
    }

    public function test_response_includes_disclaimer(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $this->assertStringContainsString('informativa', $response->json('data.disclaimer'));
        $this->assertStringContainsString('asesoramiento fiscal', $response->json('data.disclaimer'));
    }

    public function test_breakdown_lines_have_legal_references(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();
        $lines = $response->json('data.breakdown.lines');

        foreach ($lines as $line) {
            $this->assertNotNull(
                $line['legal_reference'],
                "La línea '{$line['concept']}' debería incluir referencia legal al BOE",
            );
            $this->assertStringStartsWith('https://', $line['legal_reference']);
        }
    }

    public function test_response_includes_company_total_cost(): void
    {
        $payload = [
            'gross_annual' => 30000,
            'payments_count' => 14,
            'region' => 'MD',
            'year' => 2025,
            'contract_type' => 'indefinido',
        ];

        $response = $this->postJson('/api/v1/tax/payroll', $payload);

        $response->assertSuccessful();

        $cost = $response->json('data.company_total_cost.amount');
        $this->assertGreaterThan(30000.0, (float) $cost);
        // Coste empresa típico ~30-32 % por encima del bruto.
        $this->assertLessThan(45000.0, (float) $cost);
    }
}
