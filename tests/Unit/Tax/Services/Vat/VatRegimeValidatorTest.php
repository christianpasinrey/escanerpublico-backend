<?php

namespace Tests\Unit\Tax\Services\Vat;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Services\Vat\VatRegimeValidator;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;
use PHPUnit\Framework\TestCase;

class VatRegimeValidatorTest extends TestCase
{
    private VatRegimeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new VatRegimeValidator;
    }

    private function tx(?CarbonImmutable $paidDate = null): VatTransactionInput
    {
        return new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('1000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('210.00'),
            description: 'Test',
            paidDate: $paidDate,
        );
    }

    public function test_iva_gen_passes_without_paid_date(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            quarter: 2,
        );

        $this->validator->validate($input);
        $this->assertTrue(true); // no exception
    }

    public function test_iva_caja_throws_when_paid_date_missing(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx(paidDate: null)],
            quarter: 2,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Criterio de Caja/');
        $this->validator->validate($input);
    }

    public function test_iva_caja_passes_with_paid_date(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx(paidDate: CarbonImmutable::create(2025, 5, 20))],
            quarter: 2,
        );

        $this->validator->validate($input);
        $this->assertTrue(true);
    }

    public function test_iva_simple_throws_when_modules_array_empty(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
            simplifiedModulesData: ['period_fraction' => '0.25'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/al menos un m[óo]dulo/u');
        $this->validator->validate($input);
    }

    public function test_iva_simple_passes_with_modules(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
            simplifiedModulesData: [
                'modules' => [['concept' => 'A', 'units' => '1', 'annual_quota_per_unit' => '100']],
            ],
        );

        $this->validator->validate($input);
        $this->assertTrue(true);
    }
}
