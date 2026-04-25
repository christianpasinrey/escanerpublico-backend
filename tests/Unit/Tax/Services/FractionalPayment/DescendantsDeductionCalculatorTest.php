<?php

namespace Tests\Unit\Tax\Services\FractionalPayment;

use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\FractionalPayment\DescendantsDeductionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests del DescendantsDeductionCalculator (M8).
 *
 * Verifica el cálculo de la deducción por descendientes en pagos
 * fraccionados — 100,00 € por descendiente y trimestre, art. 110.3.c LIRPF.
 */
class DescendantsDeductionCalculatorTest extends TestCase
{
    private DescendantsDeductionCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DescendantsDeductionCalculator;
    }

    public function test_sin_descendientes_devuelve_cero(): void
    {
        $situation = new TaxpayerSituation(descendants: 0);
        $this->assertSame('0.00', $this->calc->calculate($situation)->amount);
    }

    public function test_un_descendiente_devuelve_100_euros(): void
    {
        $situation = new TaxpayerSituation(descendants: 1);
        $this->assertSame('100.00', $this->calc->calculate($situation)->amount);
    }

    public function test_dos_descendientes_devuelve_200_euros(): void
    {
        $situation = new TaxpayerSituation(descendants: 2);
        $this->assertSame('200.00', $this->calc->calculate($situation)->amount);
    }

    public function test_tres_descendientes_devuelve_300_euros(): void
    {
        $situation = new TaxpayerSituation(descendants: 3);
        $this->assertSame('300.00', $this->calc->calculate($situation)->amount);
    }

    public function test_legal_reference_es_url_del_boe(): void
    {
        $this->assertStringContainsString('BOE-A-2006-20764', $this->calc->legalReference());
        $this->assertStringContainsString('a110', $this->calc->legalReference());
    }

    public function test_explanation_describe_calculo_con_hijos(): void
    {
        $exp = $this->calc->explanation(2);
        $this->assertStringContainsString('art. 110', $exp);
        $this->assertStringContainsString('100,00', $exp);
        $this->assertStringContainsString('200,00', $exp);
    }

    public function test_explanation_sin_hijos_es_clara(): void
    {
        $exp = $this->calc->explanation(0);
        $this->assertStringContainsString('Sin descendientes', $exp);
        $this->assertStringContainsString('0,00', $exp);
    }
}
