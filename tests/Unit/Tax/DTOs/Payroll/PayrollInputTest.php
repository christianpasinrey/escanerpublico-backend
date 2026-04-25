<?php

namespace Tests\Unit\Tax\DTOs\Payroll;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\DTOs\Payroll\ContractType;
use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Tests\TestCase;

class PayrollInputTest extends TestCase
{
    private function input(array $overrides = []): PayrollInput
    {
        return new PayrollInput(
            grossAnnual: $overrides['grossAnnual'] ?? Money::fromFloat(30000),
            paymentsCount: $overrides['paymentsCount'] ?? 14,
            region: $overrides['region'] ?? RegionCode::fromCode('MD'),
            year: $overrides['year'] ?? FiscalYear::fromInt(2025),
            contractType: $overrides['contractType'] ?? ContractType::Indefinido,
            birthDate: $overrides['birthDate'] ?? null,
            disabilityPercent: $overrides['disabilityPercent'] ?? null,
            descendants: $overrides['descendants'] ?? 0,
            descendantsUnder3: $overrides['descendantsUnder3'] ?? 0,
            ascendantsOver65Living: $overrides['ascendantsOver65Living'] ?? 0,
            ascendantsDisabledLiving: $overrides['ascendantsDisabledLiving'] ?? 0,
            married: $overrides['married'] ?? false,
            spouseHasIncome: $overrides['spouseHasIncome'] ?? true,
        );
    }

    public function test_accepts_12_or_14_payments(): void
    {
        $input12 = $this->input(['paymentsCount' => 12]);
        $input14 = $this->input(['paymentsCount' => 14]);

        $this->assertSame(12, $input12->paymentsCount);
        $this->assertSame(14, $input14->paymentsCount);
    }

    public function test_rejects_invalid_payments_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->input(['paymentsCount' => 13]);
    }

    public function test_rejects_negative_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->input(['grossAnnual' => Money::fromFloat(-100)]);
    }

    public function test_rejects_invalid_disability_percent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->input(['disabilityPercent' => 150]);
    }

    public function test_rejects_more_under_3_than_descendants(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->input(['descendants' => 1, 'descendantsUnder3' => 2]);
    }

    public function test_monthly_gross_with_14_payments(): void
    {
        $input = $this->input([
            'grossAnnual' => Money::fromFloat(28000),
            'paymentsCount' => 14,
        ]);

        $this->assertSame('2000.00', $input->monthlyGross()->amount);
    }

    public function test_monthly_gross_with_12_payments(): void
    {
        $input = $this->input([
            'grossAnnual' => Money::fromFloat(36000),
            'paymentsCount' => 12,
        ]);

        $this->assertSame('3000.00', $input->monthlyGross()->amount);
    }

    public function test_age_at_fiscal_year_end(): void
    {
        $input = $this->input([
            'birthDate' => CarbonImmutable::create(1980, 5, 15),
            'year' => FiscalYear::fromInt(2025),
        ]);

        $this->assertSame(45, $input->ageAtFiscalYearEnd());
    }

    public function test_age_returns_null_when_no_birth_date(): void
    {
        $input = $this->input(['birthDate' => null]);
        $this->assertNull($input->ageAtFiscalYearEnd());
    }

    public function test_contract_type_unemployment_contingency(): void
    {
        $this->assertSame('desempleo_indefinido', ContractType::Indefinido->unemploymentContingency());
        $this->assertSame('desempleo_temporal', ContractType::Temporal->unemploymentContingency());
    }
}
