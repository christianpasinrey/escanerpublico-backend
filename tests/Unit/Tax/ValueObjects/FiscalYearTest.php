<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\FiscalYear;
use Tests\TestCase;

class FiscalYearTest extends TestCase
{
    public function test_constructs_within_supported_range(): void
    {
        $y = new FiscalYear(2025);
        $this->assertSame(2025, $y->year);
    }

    public function test_rejects_below_min(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FiscalYear(2010);
    }

    public function test_start_and_end(): void
    {
        $y = new FiscalYear(2025);

        $this->assertSame('2025-01-01', $y->start()->toDateString());
        $this->assertSame('2025-12-31', $y->end()->toDateString());
    }

    public function test_quarter_boundaries(): void
    {
        $y = new FiscalYear(2025);

        $this->assertSame('2025-01-01', $y->quarterStart(1)->toDateString());
        $this->assertSame('2025-03-31', $y->quarterEnd(1)->toDateString());

        $this->assertSame('2025-04-01', $y->quarterStart(2)->toDateString());
        $this->assertSame('2025-06-30', $y->quarterEnd(2)->toDateString());

        $this->assertSame('2025-07-01', $y->quarterStart(3)->toDateString());
        $this->assertSame('2025-09-30', $y->quarterEnd(3)->toDateString());

        $this->assertSame('2025-10-01', $y->quarterStart(4)->toDateString());
        $this->assertSame('2025-12-31', $y->quarterEnd(4)->toDateString());
    }

    public function test_invalid_quarter_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FiscalYear(2025))->quarterStart(5);
    }

    public function test_previous_and_next(): void
    {
        $y = new FiscalYear(2025);

        $this->assertSame(2024, $y->previous()->year);
        $this->assertSame(2026, $y->next()->year);
    }
}
