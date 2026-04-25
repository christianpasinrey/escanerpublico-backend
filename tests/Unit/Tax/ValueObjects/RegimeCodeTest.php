<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

class RegimeCodeTest extends TestCase
{
    public function test_irpf_regimes(): void
    {
        $r = new RegimeCode('EDS');
        $this->assertSame('irpf', $r->scope());
        $this->assertTrue($r->isIrpf());
        $this->assertFalse($r->isIva());
    }

    public function test_iva_regimes(): void
    {
        $r = new RegimeCode('IVA_CAJA');
        $this->assertSame('iva', $r->scope());
        $this->assertTrue($r->isIva());
    }

    public function test_is_regimes(): void
    {
        $r = new RegimeCode('IS_STARTUP');
        $this->assertSame('is', $r->scope());
        $this->assertTrue($r->isIs());
    }

    public function test_ss_regimes(): void
    {
        $r = new RegimeCode('RETA');
        $this->assertSame('ss', $r->scope());
        $this->assertTrue($r->isSs());
    }

    public function test_unknown_regime_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegimeCode('NOPE');
    }

    public function test_all_returns_full_catalog(): void
    {
        $all = RegimeCode::all();

        $this->assertArrayHasKey('EDN', $all);
        $this->assertArrayHasKey('IVA_GEN', $all);
        $this->assertArrayHasKey('IS_GEN', $all);
        $this->assertArrayHasKey('RG', $all);
        $this->assertGreaterThan(20, count($all));
    }
}
