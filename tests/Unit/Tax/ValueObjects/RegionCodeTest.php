<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;

class RegionCodeTest extends TestCase
{
    public function test_state_factory(): void
    {
        $r = RegionCode::state();
        $this->assertSame('STATE', $r->code);
        $this->assertTrue($r->isState());
    }

    public function test_known_regions_are_valid(): void
    {
        $this->assertSame('Madrid', (new RegionCode('MD'))->name());
        $this->assertSame('Cataluña', (new RegionCode('CT'))->name());
    }

    public function test_uppercases_with_factory(): void
    {
        $r = RegionCode::fromCode('md');
        $this->assertSame('MD', $r->code);
    }

    public function test_rejects_unknown_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegionCode('ZZ');
    }

    public function test_is_foral_only_pv_navarra(): void
    {
        $this->assertTrue((new RegionCode('PV'))->isForal());
        $this->assertTrue((new RegionCode('NC'))->isForal());
        $this->assertFalse((new RegionCode('MD'))->isForal());
        $this->assertFalse(RegionCode::state()->isForal());
    }

    public function test_all_returns_complete_list(): void
    {
        $all = RegionCode::all();

        $this->assertArrayHasKey('MD', $all);
        $this->assertArrayHasKey('CT', $all);
        $this->assertCount(19, $all);
    }
}
