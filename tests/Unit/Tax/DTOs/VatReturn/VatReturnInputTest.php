<?php

namespace Tests\Unit\Tax\DTOs\VatReturn;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;
use PHPUnit\Framework\TestCase;

class VatReturnInputTest extends TestCase
{
    private function tx(): VatTransactionInput
    {
        return new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('1000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('210.00'),
            description: 'Test tx',
        );
    }

    public function test_iva_gen_quarterly_input_is_valid(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            quarter: 2,
        );

        $this->assertFalse($input->isAnnual());
        $this->assertSame('303', $input->model());
        $this->assertSame('2T 2025', $input->periodLabel());
    }

    public function test_iva_gen_annual_input_is_valid(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            quarter: null,
        );

        $this->assertTrue($input->isAnnual());
        $this->assertSame('390', $input->model());
        $this->assertSame('Anual 2025', $input->periodLabel());
    }

    public function test_throws_for_non_iva_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/scope 'iva'/");
        new VatReturnInput(
            regime: RegimeCode::fromString('EDN'), // IRPF
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
        );
    }

    public function test_throws_for_iva_regime_outside_mvp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/fuera del alcance del MVP M7/');
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_REBU'), // fuera MVP
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
        );
    }

    public function test_throws_for_oss_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_OSS'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
        );
    }

    public function test_throws_for_invalid_quarter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trimestre fuera de rango/');
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            quarter: 5,
        );
    }

    public function test_throws_when_iva_simple_without_modules_data(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/simplifiedModulesData/');
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
        );
    }

    public function test_throws_when_negative_carry_forward(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            previousQuotaCarryForward: new Money('-100.00'),
        );
    }

    public function test_iva_simple_accepts_empty_transactions_with_modules(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
            simplifiedModulesData: [
                'modules' => [
                    ['concept' => 'Personal', 'units' => '1', 'annual_quota_per_unit' => '1200.00'],
                ],
                'period_fraction' => '0.25',
            ],
        );

        $this->assertSame('IVA_SIMPLE', $input->regime->code);
        $this->assertNotNull($input->simplifiedModulesData);
    }

    public function test_throws_when_iva_gen_has_empty_transactions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/al menos una transacción/');
        new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
        );
    }
}
