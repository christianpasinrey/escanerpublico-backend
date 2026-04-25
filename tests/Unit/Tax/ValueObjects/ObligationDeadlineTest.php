<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\ObligationDeadline;
use Tests\TestCase;

class ObligationDeadlineTest extends TestCase
{
    public function test_quarterly_deadline_yields_four_dates_in_year(): void
    {
        $rule = ObligationDeadline::quarterly();
        $dates = $rule->datesFor(2025);

        $this->assertCount(4, $dates);
        $this->assertSame('2025-04-20', $dates[0]['date']);
        $this->assertSame('2025-07-20', $dates[1]['date']);
        $this->assertSame('2025-10-20', $dates[2]['date']);
        // 4T del año 2025 se presenta en enero del año siguiente
        $this->assertSame('2026-01-30', $dates[3]['date']);
    }

    public function test_is_fractional_yields_three_dates(): void
    {
        $dates = ObligationDeadline::isFractional()->datesFor(2025);

        $this->assertCount(3, $dates);
        $this->assertSame('2025-04-20', $dates[0]['date']);
        $this->assertSame('2025-10-20', $dates[1]['date']);
        $this->assertSame('2025-12-20', $dates[2]['date']);
    }

    public function test_irpf_annual_yields_one_date_year_offset(): void
    {
        $dates = ObligationDeadline::irpfAnnual()->datesFor(2024);

        $this->assertCount(1, $dates);
        // IRPF de 2024 se presenta hasta 30 junio 2025
        $this->assertSame('2025-06-30', $dates[0]['date']);
    }

    public function test_is_annual_yields_25_july_year_offset(): void
    {
        $dates = ObligationDeadline::isAnnual()->datesFor(2024);

        $this->assertCount(1, $dates);
        $this->assertSame('2025-07-25', $dates[0]['date']);
    }

    public function test_vat_annual_yields_30_january_year_offset(): void
    {
        $dates = ObligationDeadline::vatAnnual()->datesFor(2024);

        $this->assertSame('2025-01-30', $dates[0]['date']);
    }

    public function test_intracom_monthly_yields_twelve_dates(): void
    {
        $dates = ObligationDeadline::intracomMonthly()->datesFor(2025);

        $this->assertCount(12, $dates);
        $this->assertSame('2025-02-20', $dates[0]['date']); // mes 01 → presentación en feb
        $this->assertSame('2026-01-20', $dates[11]['date']); // mes 12 → enero del siguiente
    }

    public function test_from_preset_resolves_known_presets(): void
    {
        $this->assertSame(
            ObligationDeadline::PRESET_QUARTERLY,
            ObligationDeadline::fromPreset('quarterly')->preset,
        );

        $this->assertSame(
            ObligationDeadline::PRESET_IRPF_ANNUAL,
            ObligationDeadline::fromPreset('irpf_annual')->preset,
        );
    }

    public function test_from_preset_throws_on_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ObligationDeadline::fromPreset('nope');
    }

    public function test_year_out_of_range_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ObligationDeadline::quarterly()->datesFor(1899);
    }

    public function test_json_serialization_includes_all_fields(): void
    {
        $rule = ObligationDeadline::quarterly();
        $json = json_decode(json_encode($rule), true);

        $this->assertSame('quarterly', $json['preset']);
        $this->assertNotEmpty($json['description']);
        $this->assertCount(4, $json['triggers']);
    }
}
