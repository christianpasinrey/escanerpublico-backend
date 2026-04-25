<?php

namespace Tests\Unit\Tax\Services\Invoice;

use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use PHPUnit\Framework\TestCase;

/**
 * Verifica los porcentajes de Recargo de Equivalencia (art. 161 LIVA):
 *  - 21 % → 5,2 %
 *  - 10 % → 1,4 %
 *  - 4 %  → 0,5 %
 *  - 5 %  → 0,62 %
 *  - 0 % / Exento → 0 %
 */
class SurchargeEquivalenceResolverTest extends TestCase
{
    private SurchargeEquivalenceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SurchargeEquivalenceResolver;
    }

    public function test_recargo_general_es_5_2(): void
    {
        $rate = $this->resolver->resolve(VatRateType::GENERAL);
        $this->assertSame('5.20', $rate->percentage);
    }

    public function test_recargo_reducido_es_1_4(): void
    {
        $rate = $this->resolver->resolve(VatRateType::REDUCED);
        $this->assertSame('1.40', $rate->percentage);
    }

    public function test_recargo_superreducido_es_0_5(): void
    {
        $rate = $this->resolver->resolve(VatRateType::SUPER_REDUCED);
        $this->assertSame('0.50', $rate->percentage);
    }

    public function test_recargo_especial_es_0_62(): void
    {
        $rate = $this->resolver->resolve(VatRateType::SPECIAL);
        $this->assertSame('0.62', $rate->percentage);
    }

    public function test_recargo_zero_y_exento_son_cero(): void
    {
        $this->assertSame('0.00', $this->resolver->resolve(VatRateType::ZERO)->percentage);
        $this->assertSame('0.00', $this->resolver->resolve(VatRateType::EXEMPT)->percentage);
    }
}
