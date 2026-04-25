<?php

namespace Tests\Unit\Tax\DTOs\Invoice;

use Modules\Tax\DTOs\Invoice\VatRateType;
use PHPUnit\Framework\TestCase;

class VatRateTypeTest extends TestCase
{
    public function test_default_rate_percentages_match_liva(): void
    {
        $this->assertSame('21.00', VatRateType::GENERAL->defaultRatePercentage());
        $this->assertSame('10.00', VatRateType::REDUCED->defaultRatePercentage());
        $this->assertSame('4.00', VatRateType::SUPER_REDUCED->defaultRatePercentage());
        $this->assertSame('5.00', VatRateType::SPECIAL->defaultRatePercentage());
        $this->assertSame('0.00', VatRateType::ZERO->defaultRatePercentage());
        $this->assertSame('0.00', VatRateType::EXEMPT->defaultRatePercentage());
    }

    public function test_label_in_spanish(): void
    {
        $this->assertStringContainsString('21', VatRateType::GENERAL->label());
        $this->assertStringContainsString('10', VatRateType::REDUCED->label());
        $this->assertStringContainsString('4', VatRateType::SUPER_REDUCED->label());
        $this->assertSame('Exento', VatRateType::EXEMPT->label());
    }

    public function test_from_string_matches_enum_value(): void
    {
        $this->assertSame(VatRateType::GENERAL, VatRateType::from('general'));
        $this->assertSame(VatRateType::SUPER_REDUCED, VatRateType::from('super_reduced'));
    }
}
