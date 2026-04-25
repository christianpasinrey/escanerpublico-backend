<?php

namespace Tests\Unit\Tax\Services\Vat;

use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use PHPUnit\Framework\TestCase;

class VatPeriodResolverTest extends TestCase
{
    private VatPeriodResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VatPeriodResolver;
    }

    public function test_resolves_quarter_1_dates(): void
    {
        $period = $this->resolver->resolve(FiscalYear::fromInt(2025), 1);

        $this->assertSame('2025-01-01', $period->from?->toDateString());
        $this->assertSame('2025-03-31', $period->to?->toDateString());
    }

    public function test_resolves_quarter_2_dates(): void
    {
        $period = $this->resolver->resolve(FiscalYear::fromInt(2025), 2);

        $this->assertSame('2025-04-01', $period->from?->toDateString());
        $this->assertSame('2025-06-30', $period->to?->toDateString());
    }

    public function test_resolves_quarter_3_dates(): void
    {
        $period = $this->resolver->resolve(FiscalYear::fromInt(2025), 3);

        $this->assertSame('2025-07-01', $period->from?->toDateString());
        $this->assertSame('2025-09-30', $period->to?->toDateString());
    }

    public function test_resolves_quarter_4_dates(): void
    {
        $period = $this->resolver->resolve(FiscalYear::fromInt(2025), 4);

        $this->assertSame('2025-10-01', $period->from?->toDateString());
        $this->assertSame('2025-12-31', $period->to?->toDateString());
    }

    public function test_resolves_annual_period(): void
    {
        $period = $this->resolver->resolve(FiscalYear::fromInt(2025), null);

        $this->assertSame('2025-01-01', $period->from?->toDateString());
        $this->assertSame('2025-12-31', $period->to?->toDateString());
    }

    public function test_is_last_period_returns_true_for_quarter_4_and_null(): void
    {
        $this->assertTrue($this->resolver->isLastPeriod(4));
        $this->assertTrue($this->resolver->isLastPeriod(null));
    }

    public function test_is_last_period_returns_false_for_intermediate_quarters(): void
    {
        $this->assertFalse($this->resolver->isLastPeriod(1));
        $this->assertFalse($this->resolver->isLastPeriod(2));
        $this->assertFalse($this->resolver->isLastPeriod(3));
    }

    public function test_label_for_quarter_and_annual(): void
    {
        $this->assertSame('1T 2025', $this->resolver->label(FiscalYear::fromInt(2025), 1));
        $this->assertSame('Anual 2025', $this->resolver->label(FiscalYear::fromInt(2025), null));
    }
}
