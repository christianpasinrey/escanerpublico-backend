<?php

namespace Tests\Feature\Tax\Calculators\FractionalPayment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tax\Calculators\FractionalPayment\Modelo131Payment;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\FractionalPayment\DescendantsDeductionCalculator;
use Modules\Tax\Services\IncomeTax\EoModulesCalculator;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

/**
 * Golden tests para Modelo131Payment (pagos fraccionados Estimación
 * Objetiva — módulos).
 *
 * Cada caso cita BOE: Ley 35/2006 LIRPF arts. 99-110, RD 439/2007 RIRPF
 * art. 110.1.b, Orden HFP/3666/2024 modelo 131, Orden HFP/1397/2024 módulos
 * 2025.
 *
 * Los importes están calculados con las reglas de bcmath (truncamiento a
 * 2 decimales) usando los valores de la Orden HFP/1397/2024 sembrados en
 * EoModulesDataProvider para 2025.
 */
class Modelo131PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): Modelo131Payment
    {
        return new Modelo131Payment(
            new DescendantsDeductionCalculator,
            new EoModulesCalculator,
            new VatPeriodResolver,
        );
    }

    /**
     * @param  array<string, float|int>  $modulesData
     */
    private function input(
        string $activityCode = '721.2',
        array $modulesData = ['personal_no_asalariado' => 1, 'distancia_km' => 80],
        int $quarter = 4,
        int $salariedEmployees = 0,
        Money $withholdings = new Money('0.00'),
        Money $previous = new Money('0.00'),
        TaxpayerSituation $situation = new TaxpayerSituation,
    ): FractionalPaymentInput {
        return new FractionalPaymentInput(
            model: FractionalPaymentModel::MODELO_131,
            regime: new RegimeCode('EO'),
            year: FiscalYear::fromInt(2025),
            quarter: $quarter,
            taxpayerSituation: $situation,
            activityCode: $activityCode,
            eoModulesData: $modulesData,
            salariedEmployees: $salariedEmployees,
            withholdingsApplied: $withholdings,
            previousQuartersPayments: $previous,
        );
    }

    /**
     * GOLDEN — Taxi 721.2 Q4 2025 sin asalariados (4 %).
     *
     * Módulos declarados: 1 titular (personal_no_asalariado), 80.000 km/año
     * (80 unidades de distancia_km, valor unitario 24,06 €/1.000 km).
     *
     * Rendimiento previo (BOE-A-2024-26896 Anexo II):
     *   1 × 4.076,60 + 80 × 24,06 = 4.076,60 + 1.924,80 = 6.001,40 €.
     *
     * Reducción 5 % (DT 32ª LIRPF):
     *   6.001,40 × 5 % = 300,0700 → bcmath truncado a 300,07 €.
     *
     * Cuota anual EO (rendimiento neto definitivo):
     *   6.001,40 − 300,07 = 5.701,33 €.
     *
     * Tipo aplicable (art. 110.1.b RIRPF, sin asalariados): 4 %.
     * Pago bruto: 5.701,33 × 4 % = 228,0532 → bcmath truncado a 228,05 €.
     *
     * Sin descendientes, sin retenciones, sin pagos anteriores.
     * Resultado: 228,05 € a ingresar.
     */
    public function test_golden_eo_taxi_721_2_q4_2025_sin_asalariados(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            quarter: 4,
            salariedEmployees: 0,
        ));

        $this->assertSame('131', $result->model);
        $this->assertSame('4T 2025', $result->period);
        $this->assertSame('5701.33', $result->cumulativeNetIncome->amount);
        $this->assertSame('4.00', $result->applicableRate->percentage);
        $this->assertSame('228.05', $result->grossPayment->amount);
        $this->assertSame('228.05', $result->result->amount);
        $this->assertTrue($result->isToPay());
    }

    /**
     * GOLDEN — Bar 673.2 Q1 2025 con 2 asalariados (2 %).
     *
     * Módulos declarados: 2 personal_asalariado, 1 titular.
     *
     * Rendimiento previo (BOE-A-2024-26896 Anexo II 673.2):
     *   2 × 1.136,08 + 1 × 9.551,05 = 2.272,16 + 9.551,05 = 11.823,21 €.
     *
     * Reducción 5 % (DT 32ª LIRPF):
     *   11.823,21 × 5 % = 591,1605 → bcmath truncado a 591,16 €.
     *
     * Cuota anual EO: 11.823,21 − 591,16 = 11.232,05 €.
     *
     * Tipo aplicable (≥ 2 asalariados): 2 %.
     * Pago bruto: 11.232,05 × 2 % = 224,6410 → 224,64 €.
     *
     * Resultado: 224,64 € a ingresar.
     */
    public function test_golden_eo_bar_673_2_q1_2025_con_2_asalariados(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '673.2',
            modulesData: ['personal_asalariado' => 2, 'personal_no_asalariado' => 1],
            quarter: 1,
            salariedEmployees: 2,
        ));

        $this->assertSame('1T 2025', $result->period);
        $this->assertSame('11232.05', $result->cumulativeNetIncome->amount);
        $this->assertSame('2.00', $result->applicableRate->percentage);
        $this->assertSame('224.64', $result->grossPayment->amount);
        $this->assertSame('224.64', $result->result->amount);
    }

    /**
     * GOLDEN — Restaurante 671.4 Q2 2025 con 1 asalariado (3 %).
     *
     * Módulos: 1 personal_asalariado, 1 titular, 6 mesas.
     *
     * Rendimiento previo (BOE-A-2024-26896 Anexo II 671.4):
     *   1 × 11.960,99 + 1 × 22.489,46 + 6 × 750,93
     *   = 11.960,99 + 22.489,46 + 4.505,58 = 38.956,03 €.
     *
     * Reducción 5 %: 38.956,03 × 5 % = 1.947,8015 → 1.947,80 €.
     * Cuota anual EO: 38.956,03 − 1.947,80 = 37.008,23 €.
     *
     * Tipo (1 asalariado): 3 %.
     * Pago bruto: 37.008,23 × 3 % = 1.110,2469 → 1.110,24 €.
     *
     * Resultado: 1.110,24 € a ingresar.
     */
    public function test_golden_eo_restaurante_671_4_q2_2025_con_1_asalariado(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '671.4',
            modulesData: ['personal_asalariado' => 1, 'personal_no_asalariado' => 1, 'mesas' => 6],
            quarter: 2,
            salariedEmployees: 1,
        ));

        $this->assertSame('37008.23', $result->cumulativeNetIncome->amount);
        $this->assertSame('3.00', $result->applicableRate->percentage);
        $this->assertSame('1110.24', $result->grossPayment->amount);
        $this->assertSame('1110.24', $result->result->amount);
    }

    /**
     * EDGE — Modelo 131 con descendientes, retenciones y pagos previos.
     *
     * Mismo taxi de antes (gross 228,05). Con 1 hijo (deducción 100 €),
     * retenciones 50 €, pagos previos Q1+Q2+Q3 = 80 €.
     * Resultado: 228,05 − 100 − 50 − 80 = −1,95 → truncado a 0,00 €.
     */
    public function test_edge_taxi_q4_con_deducciones_excede_bruto_truncado(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            quarter: 4,
            salariedEmployees: 0,
            withholdings: new Money('50.00'),
            previous: new Money('80.00'),
            situation: new TaxpayerSituation(descendants: 1),
        ));

        $this->assertSame('228.05', $result->grossPayment->amount);
        $this->assertSame('100.00', $result->deductionDescendants->amount);
        $this->assertSame('0.00', $result->result->amount);
        $this->assertTrue($result->isClampedToZero());
    }

    /**
     * Tipo aplicable cambia con el número de asalariados:
     *   0 → 4 %; 1 → 3 %; 2+ → 2 % (art. 110.1.b RIRPF).
     */
    public function test_tipo_aplicable_segun_asalariados(): void
    {
        $modules = ['personal_no_asalariado' => 1, 'distancia_km' => 80];

        $r0 = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: $modules,
            quarter: 1,
            salariedEmployees: 0,
        ));
        $this->assertSame('4.00', $r0->applicableRate->percentage);

        $r1 = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: $modules,
            quarter: 1,
            salariedEmployees: 1,
        ));
        $this->assertSame('3.00', $r1->applicableRate->percentage);

        $r2 = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: $modules,
            quarter: 1,
            salariedEmployees: 2,
        ));
        $this->assertSame('2.00', $r2->applicableRate->percentage);

        $r5 = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: $modules,
            quarter: 1,
            salariedEmployees: 5,
        ));
        $this->assertSame('2.00', $r5->applicableRate->percentage);
    }

    /**
     * Actividad EO no soportada en MVP → InvalidArgumentException
     * propagada desde EoModulesCalculator.
     */
    public function test_actividad_no_soportada_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no está cubierto/');

        $this->calc()->calculate($this->input(
            activityCode: '999.9',
            modulesData: ['personal_no_asalariado' => 1],
        ));
    }

    /**
     * El breakdown debe contener líneas INFO por cada módulo declarado.
     */
    public function test_breakdown_contiene_lineas_de_modulos(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            quarter: 1,
        ));

        $infoLines = array_filter(
            $result->breakdown->lines,
            fn ($l) => $l->category === BreakdownCategory::INFO,
        );
        // 2 módulos con units > 0 → 2 líneas INFO.
        $this->assertCount(2, $infoLines);
    }

    /**
     * Meta del breakdown contiene metadatos de la actividad y disclaimer.
     */
    public function test_meta_breakdown_completo(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            quarter: 4,
            salariedEmployees: 0,
        ));

        $meta = $result->breakdown->meta;
        $this->assertSame('131', $meta['model']);
        $this->assertSame('EO', $meta['regime']);
        $this->assertSame(2025, $meta['fiscal_year']);
        $this->assertSame(4, $meta['quarter']);
        $this->assertSame('721.2', $meta['activity_code']);
        $this->assertSame(0, $meta['salaried_employees']);
        $this->assertSame('4.00', $meta['applicable_rate_percent']);
        $this->assertArrayHasKey('disclaimer', $meta);
        $this->assertStringContainsString('LIRPF', $meta['disclaimer']);
    }

    /**
     * Cada línea del breakdown debe llevar referencia BOE no vacía.
     */
    public function test_cada_linea_lleva_referencia_boe(): void
    {
        $result = $this->calc()->calculate($this->input(
            activityCode: '721.2',
            modulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            quarter: 4,
            salariedEmployees: 0,
            withholdings: new Money('10.00'),
            previous: new Money('5.00'),
            situation: new TaxpayerSituation(descendants: 1),
        ));

        foreach ($result->breakdown->lines as $line) {
            $this->assertNotNull($line->legalReference, "Línea sin BOE: {$line->concept}");
            $this->assertStringContainsString('boe.es', $line->legalReference);
        }
    }
}
