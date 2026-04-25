<?php

namespace Tests\Unit\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Tests\TestCase;

class TaxpayerSituationTest extends TestCase
{
    public function test_default_construction_creates_single_taxpayer(): void
    {
        $s = new TaxpayerSituation;

        $this->assertFalse($s->married);
        $this->assertSame(0, $s->descendants);
        $this->assertNull($s->disabilityPercent);
        $this->assertNull($s->ageAtYearEnd);
    }

    public function test_static_single_helper(): void
    {
        $s = TaxpayerSituation::single();

        $this->assertSame(0, $s->descendants);
        $this->assertFalse($s->married);
    }

    public function test_rejects_negative_descendants(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxpayerSituation(descendants: -1);
    }

    public function test_rejects_descendants_under_3_greater_than_descendants(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxpayerSituation(descendants: 1, descendantsUnder3: 2);
    }

    public function test_rejects_negative_ascendants(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxpayerSituation(ascendantsOver65Living: -1);
    }

    public function test_rejects_disability_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxpayerSituation(disabilityPercent: 150);
    }

    public function test_rejects_age_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TaxpayerSituation(ageAtYearEnd: 200);
    }

    public function test_serializes_to_json(): void
    {
        $s = new TaxpayerSituation(
            married: true,
            descendants: 2,
            descendantsUnder3: 1,
            ascendantsOver65Living: 1,
            disabilityPercent: 35,
            ageAtYearEnd: 40,
        );

        $arr = $s->jsonSerialize();

        $this->assertTrue($arr['married']);
        $this->assertSame(2, $arr['descendants']);
        $this->assertSame(1, $arr['descendants_under_3']);
        $this->assertSame(35, $arr['disability_percent']);
        $this->assertSame(40, $arr['age_at_year_end']);
    }
}
