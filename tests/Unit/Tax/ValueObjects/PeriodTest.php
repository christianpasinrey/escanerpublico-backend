<?php

namespace Tests\Unit\Tax\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Period;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    public function test_open_period_contains_any_date(): void
    {
        $p = Period::open();

        $this->assertTrue($p->contains(CarbonImmutable::create(1900, 1, 1)));
        $this->assertTrue($p->contains(CarbonImmutable::create(2099, 12, 31)));
    }

    public function test_from_year_factory(): void
    {
        $p = Period::fromYear(new FiscalYear(2025));

        $this->assertTrue($p->contains(CarbonImmutable::create(2025, 6, 15)));
        $this->assertFalse($p->contains(CarbonImmutable::create(2024, 12, 31)));
        $this->assertFalse($p->contains(CarbonImmutable::create(2026, 1, 1)));
    }

    public function test_invalid_range_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Period(
            CarbonImmutable::create(2025, 12, 31),
            CarbonImmutable::create(2025, 1, 1),
        );
    }

    public function test_overlaps_detects_intersection(): void
    {
        $a = new Period(
            CarbonImmutable::create(2024, 1, 1),
            CarbonImmutable::create(2024, 12, 31),
        );
        $b = new Period(
            CarbonImmutable::create(2024, 6, 1),
            CarbonImmutable::create(2025, 6, 1),
        );
        $disjoint = new Period(
            CarbonImmutable::create(2026, 1, 1),
            null,
        );

        $this->assertTrue($a->overlaps($b));
        $this->assertTrue($b->overlaps($a));
        $this->assertFalse($a->overlaps($disjoint));
    }
}
