<?php

namespace Tests\Feature\Tax\Calculators\FractionalPayment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\FractionalPayment\Modelo130Payment;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\FractionalPayment\DescendantsDeductionCalculator;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Golden tests para Modelo130Payment (pagos fraccionados EDN/EDS).
 *
 * Cada caso cita la fuente BOE (Ley 35/2006 LIRPF, RD 439/2007 RIRPF,
 * Orden HFP/3666/2024 modelo 130).
 *
 * Sistema acumulativo: cada trimestre se calcula sobre el total del año
 * desde 1-enero hasta el último día del trimestre, restando los pagos
 * fraccionados ya ingresados en trimestres anteriores.
 */
class Modelo130PaymentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private function calc(): Modelo130Payment
    {
        return new Modelo130Payment(
            new DescendantsDeductionCalculator,
            new VatPeriodResolver,
            $this->app->make(TaxParameterRepository::class),
        );
    }

    private function input(array $overrides = []): FractionalPaymentInput
    {
        $defaults = [
            'model' => FractionalPaymentModel::MODELO_130,
            'regime' => new RegimeCode('EDN'),
            'year' => FiscalYear::fromInt(2025),
            'quarter' => 1,
            'taxpayerSituation' => new TaxpayerSituation,
            'cumulativeGrossRevenue' => new Money('12000.00'),
            'cumulativeDeductibleExpenses' => new Money('3000.00'),
        ];
        $args = array_merge($defaults, $overrides);

        return new FractionalPaymentInput(
            model: $args['model'],
            regime: $args['regime'],
            year: $args['year'],
            quarter: $args['quarter'],
            taxpayerSituation: $args['taxpayerSituation'],
            cumulativeGrossRevenue: $args['cumulativeGrossRevenue'],
            cumulativeDeductibleExpenses: $args['cumulativeDeductibleExpenses'],
            withholdingsApplied: $args['withholdingsApplied'] ?? new Money('0.00'),
            previousQuartersPayments: $args['previousQuartersPayments'] ?? new Money('0.00'),
        );
    }

    /**
     * GOLDEN — EDN profesional Q1 2025.
     *
     * Ingresos íntegros acumulados Q1: 12.000,00 €.
     * Gastos deducibles acumulados Q1:  3.000,00 €.
     * Rendimiento neto: 12.000 − 3.000 = 9.000,00 €.
     * Pago bruto: 9.000 × 20 % = 1.800,00 €.
     * Sin descendientes, sin retenciones, sin trimestres anteriores.
     * Resultado: 1.800,00 € a ingresar.
     *
     * Fuentes: art. 110.1.a LIRPF (BOE-A-2006-20764);
     *          art. 110.1.a RIRPF (BOE-A-2007-6820);
     *          Orden HFP/3666/2024 (BOE-A-2024-26173).
     */
    public function test_golden_edn_q1_2025_caso_basico(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input());

        $this->assertSame('130', $result->model);
        $this->assertSame('1T 2025', $result->period);
        $this->assertSame('9000.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('20.00', $result->applicableRate->percentage);
        $this->assertSame('1800.00', $result->grossPayment->amount);
        $this->assertSame('0.00', $result->deductionDescendants->amount);
        $this->assertSame('1800.00', $result->result->amount);
        $this->assertTrue($result->isToPay());
        $this->assertFalse($result->isClampedToZero());
    }

    /**
     * GOLDEN — EDS Q2 2025 con reducción genérica 7 %.
     *
     * Ingresos íntegros acumulados Q2: 24.000,00 €.
     * Gastos deducibles acumulados Q2:  6.000,00 €.
     * Rendimiento previo: 24.000 − 6.000 = 18.000,00 €.
     * Reducción genérica EDS al 7 % (Ley 31/2022 — vigente 2023-2025
     * por Ley 7/2024): 18.000 × 7 % = 1.260,00 €. Tope anual 2.000 €,
     * no aplicable (1.260 < 2.000).
     * Rendimiento neto: 18.000 − 1.260 = 16.740,00 €.
     * Pago bruto: 16.740 × 20 % = 3.348,00 €.
     * Pagos trimestres anteriores (Q1): 1.800,00 €.
     * Resultado: 3.348 − 1.800 = 1.548,00 € a ingresar.
     *
     * NOTA: el spec original M8 indicaba 5 % (900 €) pero ese tipo está
     * derogado desde 2023; el parámetro `irpf.gastos_genericos_eds_porcentaje`
     * sembrado en BD es 7,0 (Ley 31/2022, BOE-A-2022-22128, mantenido en
     * 2024-2025 por Ley 7/2024, BOE-A-2024-26905).
     *
     * Fuentes: art. 30.2.4ª RIRPF; Ley 31/2022 art. 64.
     */
    public function test_golden_eds_q2_2025_con_reduccion_generica(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'regime' => new RegimeCode('EDS'),
            'quarter' => 2,
            'cumulativeGrossRevenue' => new Money('24000.00'),
            'cumulativeDeductibleExpenses' => new Money('6000.00'),
            'previousQuartersPayments' => new Money('1800.00'),
        ]));

        $this->assertSame('16740.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('3348.00', $result->grossPayment->amount);
        $this->assertSame('1800.00', $result->previousQuartersDeducted->amount);
        $this->assertSame('1548.00', $result->result->amount);
        $this->assertTrue($result->isToPay());
    }

    /**
     * GOLDEN — EDN Q3 2025 con 2 hijos: deducciones llevan a 0,00 €.
     *
     * Ingresos: 30.000,00 €. Gastos: 8.000,00 €.
     * Rendimiento neto: 22.000,00 €. Pago bruto: 4.400,00 €.
     * Deducción descendientes: 2 × 100 €/trim = 200,00 €
     *   (art. 110.3.c LIRPF — 100 €/descendiente y trimestre).
     * Retenciones IRPF soportadas en facturas profesionales: 3.000,00 €.
     * Pagos Q1+Q2 ya ingresados: 2.500,00 €.
     * Bruto resultado: 4.400 − 200 − 3.000 − 2.500 = −1.300,00 €.
     * Como el modelo 130 NUNCA es a devolver (art. 110 RIRPF), el
     * resultado se trunca a 0,00 €.
     *
     * Fuentes: art. 110.3 LIRPF (BOE-A-2006-20764);
     *          art. 110 RIRPF (BOE-A-2007-6820).
     */
    public function test_golden_edn_q3_2025_con_2_hijos_se_trunca_a_cero(): void
    {
        $this->seedTaxParameters(2025);

        $situation = new TaxpayerSituation(descendants: 2);

        $result = $this->calc()->calculate($this->input([
            'quarter' => 3,
            'taxpayerSituation' => $situation,
            'cumulativeGrossRevenue' => new Money('30000.00'),
            'cumulativeDeductibleExpenses' => new Money('8000.00'),
            'withholdingsApplied' => new Money('3000.00'),
            'previousQuartersPayments' => new Money('2500.00'),
        ]));

        $this->assertSame('22000.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('4400.00', $result->grossPayment->amount);
        $this->assertSame('200.00', $result->deductionDescendants->amount);
        $this->assertSame('0.00', $result->result->amount);
        $this->assertFalse($result->isToPay());
        $this->assertTrue($result->isClampedToZero());
        $this->assertTrue($result->breakdown->meta['clamped_to_zero']);
    }

    /**
     * EDGE — Rendimiento neto negativo en modelo 130 → resultado 0,00 €.
     *
     * Ingresos: 5.000 €; Gastos: 7.000 €.
     * Rendimiento previo: −2.000 € → base truncada a 0.
     * Pago bruto: 0 × 20 % = 0,00 €.
     * Resultado: 0,00 € (sin pago, sin devolución).
     *
     * Fuente: art. 110.1.a RIRPF (la base nunca puede ser negativa).
     */
    public function test_edge_rendimiento_negativo_resultado_cero(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'cumulativeGrossRevenue' => new Money('5000.00'),
            'cumulativeDeductibleExpenses' => new Money('7000.00'),
        ]));

        $this->assertSame('0.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('0.00', $result->grossPayment->amount);
        $this->assertSame('0.00', $result->result->amount);
        $this->assertFalse($result->isToPay());
    }

    /**
     * EDGE — Pagos previos > pago bruto → resultado 0,00 €, no devolución.
     *
     * Ingresos 12.000 − Gastos 3.000 = 9.000. Pago bruto 1.800 €.
     * Pagos Q1+Q2 anteriores: 5.000 € (ej. ajuste tras renta del año).
     * Bruto: 1.800 − 5.000 = −3.200 → truncado a 0,00 €.
     */
    public function test_edge_pagos_previos_mayores_que_bruto_truncado(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'previousQuartersPayments' => new Money('5000.00'),
        ]));

        $this->assertSame('1800.00', $result->grossPayment->amount);
        $this->assertSame('5000.00', $result->previousQuartersDeducted->amount);
        $this->assertSame('0.00', $result->result->amount);
        $this->assertTrue($result->isClampedToZero());
    }

    /**
     * EDGE — Retenciones soportadas > pago bruto → resultado 0,00 €.
     *
     * Pago bruto 1.800 €. Retenciones 2.500 €.
     * 1.800 − 2.500 = −700 → 0,00 €.
     */
    public function test_edge_retenciones_mayores_que_bruto_truncado(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'withholdingsApplied' => new Money('2500.00'),
        ]));

        $this->assertSame('0.00', $result->result->amount);
    }

    /**
     * EDS Q1 2025 — Tope de 2.000 € en reducción genérica.
     *
     * Rendimiento previo muy alto (50.000 €): 7 % × 50.000 = 3.500 €.
     * Como supera el tope anual 2.000 €, se aplica el tope.
     * Rendimiento neto: 50.000 − 2.000 = 48.000 €.
     * Pago bruto: 48.000 × 20 % = 9.600,00 €.
     */
    public function test_eds_aplica_tope_2000_en_reduccion_generica(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'regime' => new RegimeCode('EDS'),
            'cumulativeGrossRevenue' => new Money('60000.00'),
            'cumulativeDeductibleExpenses' => new Money('10000.00'),
        ]));

        // Previo = 50.000; reducción capada a 2.000; neto = 48.000; bruto = 9.600.
        $this->assertSame('48000.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('9600.00', $result->grossPayment->amount);
    }

    /**
     * Verificación: el breakdown contiene una línea NET con la categoría
     * adecuada y el meta incluye disclaimer y clamped_to_zero.
     */
    public function test_breakdown_estructura_con_disclaimer_y_meta(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input());
        $breakdown = $result->breakdown;

        $netLines = array_filter(
            $breakdown->lines,
            fn ($l) => $l->category === BreakdownCategory::NET,
        );
        $this->assertCount(1, $netLines);

        $this->assertArrayHasKey('disclaimer', $breakdown->meta);
        $this->assertArrayHasKey('clamped_to_zero', $breakdown->meta);
        $this->assertArrayHasKey('period_label', $breakdown->meta);
        $this->assertSame('1T 2025', $breakdown->meta['period_label']);
        $this->assertStringContainsString('LIRPF', $breakdown->meta['disclaimer']);
    }

    /**
     * Cada línea del breakdown debe llevar referencia BOE no vacía.
     */
    public function test_cada_linea_lleva_referencia_boe(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'taxpayerSituation' => new TaxpayerSituation(descendants: 1),
            'withholdingsApplied' => new Money('100.00'),
            'previousQuartersPayments' => new Money('500.00'),
        ]));

        foreach ($result->breakdown->lines as $line) {
            $this->assertNotNull($line->legalReference, "Línea sin BOE: {$line->concept}");
            $this->assertStringContainsString('boe.es', $line->legalReference);
        }
    }

    /**
     * EDN Q4 2025 — caso completo con todas las deducciones aplicables.
     *
     * Ingresos 50.000 − Gastos 12.000 = 38.000.
     * Pago bruto: 38.000 × 20 % = 7.600,00 €.
     * Descendientes: 1 × 100 = 100 €.
     * Retenciones: 2.000 €. Pagos previos (Q1+Q2+Q3): 4.000 €.
     * Resultado: 7.600 − 100 − 2.000 − 4.000 = 1.500,00 €.
     */
    public function test_golden_edn_q4_2025_caso_completo_con_descendientes(): void
    {
        $this->seedTaxParameters(2025);

        $result = $this->calc()->calculate($this->input([
            'quarter' => 4,
            'taxpayerSituation' => new TaxpayerSituation(descendants: 1),
            'cumulativeGrossRevenue' => new Money('50000.00'),
            'cumulativeDeductibleExpenses' => new Money('12000.00'),
            'withholdingsApplied' => new Money('2000.00'),
            'previousQuartersPayments' => new Money('4000.00'),
        ]));

        $this->assertSame('38000.00', $result->cumulativeNetIncome->amount);
        $this->assertSame('7600.00', $result->grossPayment->amount);
        $this->assertSame('100.00', $result->deductionDescendants->amount);
        $this->assertSame('1500.00', $result->result->amount);
        $this->assertSame('4T 2025', $result->period);
    }

    /**
     * El modelo en el Breakdown.meta es siempre '130' independientemente
     * del régimen (EDN o EDS).
     */
    public function test_meta_modelo_siempre_130_para_estimacion_directa(): void
    {
        $this->seedTaxParameters(2025);

        $resultEdn = $this->calc()->calculate($this->input());
        $this->assertSame('130', $resultEdn->breakdown->meta['model']);
        $this->assertSame('EDN', $resultEdn->breakdown->meta['regime']);

        $resultEds = $this->calc()->calculate($this->input([
            'regime' => new RegimeCode('EDS'),
        ]));
        $this->assertSame('130', $resultEds->breakdown->meta['model']);
        $this->assertSame('EDS', $resultEds->breakdown->meta['regime']);
    }
}
