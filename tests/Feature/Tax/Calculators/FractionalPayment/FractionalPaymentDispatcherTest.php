<?php

namespace Tests\Feature\Tax\Calculators\FractionalPayment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tax\Calculators\FractionalPayment\FractionalPaymentCalculator;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;
use Tests\Unit\Tax\Calculators\Payroll\SeedsTaxParameters;

/**
 * Tests del dispatcher FractionalPaymentCalculator (M8).
 *
 * Verifica que el dispatcher elige correctamente la implementación según
 * el modelo (130 → Modelo130Payment; 131 → Modelo131Payment) y rechaza
 * combinaciones incompatibles a través del DTO o del helper.
 */
class FractionalPaymentDispatcherTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTaxParameters;

    private function dispatcher(): FractionalPaymentCalculator
    {
        return $this->app->make(FractionalPaymentCalculator::class);
    }

    public function test_dispatcher_elige_modelo_130_para_edn(): void
    {
        $this->seedTaxParameters(2025);

        $input = new FractionalPaymentInput(
            model: FractionalPaymentModel::MODELO_130,
            regime: new RegimeCode('EDN'),
            year: FiscalYear::fromInt(2025),
            quarter: 1,
            taxpayerSituation: new TaxpayerSituation,
            cumulativeGrossRevenue: new Money('12000.00'),
            cumulativeDeductibleExpenses: new Money('3000.00'),
        );

        $result = $this->dispatcher()->calculate($input);
        $this->assertSame('130', $result->model);
        $this->assertSame('1800.00', $result->result->amount);
    }

    public function test_dispatcher_elige_modelo_131_para_eo(): void
    {
        $input = new FractionalPaymentInput(
            model: FractionalPaymentModel::MODELO_131,
            regime: new RegimeCode('EO'),
            year: FiscalYear::fromInt(2025),
            quarter: 4,
            taxpayerSituation: new TaxpayerSituation,
            activityCode: '721.2',
            eoModulesData: ['personal_no_asalariado' => 1, 'distancia_km' => 80],
            salariedEmployees: 0,
        );

        $result = $this->dispatcher()->calculate($input);
        $this->assertSame('131', $result->model);
        $this->assertSame('228.05', $result->result->amount);
    }

    public function test_assert_compatible_lanza_para_combinacion_incompatible(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->dispatcher()->assertCompatible(
            FractionalPaymentModel::MODELO_130,
            'EO',
        );
    }

    public function test_assert_compatible_pasa_para_combinacion_valida(): void
    {
        // No exception expected.
        $this->dispatcher()->assertCompatible(
            FractionalPaymentModel::MODELO_130,
            'EDN',
        );
        $this->dispatcher()->assertCompatible(
            FractionalPaymentModel::MODELO_130,
            'EDS',
        );
        $this->dispatcher()->assertCompatible(
            FractionalPaymentModel::MODELO_131,
            'EO',
        );
        $this->assertTrue(true); // Placeholder explícito tras 3 asserts implícitos.
    }
}
