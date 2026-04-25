<?php

namespace Tests\Feature\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tax\Calculators\Vat\VatReturnCalculator;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\Services\Vat\VatRegimeValidator;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;
use Tests\TestCase;

/**
 * Tests del dispatcher VatReturnCalculator.
 */
class VatReturnDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): VatReturnCalculator
    {
        return new VatReturnCalculator(
            new VatRegimeValidator,
            new VatPeriodResolver,
            new Modelo303CasillasMapper,
        );
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

    public function test_dispatches_iva_gen_to_regimen_general(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx()],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
    }

    public function test_dispatches_iva_caja_to_criterio_caja(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx(CarbonImmutable::create(2025, 5, 10))],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
    }

    public function test_dispatches_iva_simple_to_simplificado(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: 1,
            simplifiedModulesData: [
                'modules' => [['concept' => 'Personal', 'units' => '1', 'annual_quota_per_unit' => '1200.00']],
                'period_fraction' => '0.25',
            ],
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('300.00', $result->totalVatAccrued->amount);
    }

    public function test_iva_caja_without_paid_date_throws_via_validator(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [$this->tx(paidDate: null)],
            quarter: 2,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Criterio de Caja/');
        $this->calc()->calculate($input);
    }
}
