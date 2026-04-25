<?php

namespace Tests\Feature\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Vat\RegimenSimplificadoVat;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnStatus;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;
use Tests\TestCase;

/**
 * Golden tests para RegimenSimplificadoVat.
 *
 * Fuente: art. 122-123 LIVA, RD 1624/1992 RIVA arts. 34-43,
 * Anexo II Orden anual de módulos (HAC/1167/2024 para 2025).
 */
class RegimenSimplificadoVatTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): RegimenSimplificadoVat
    {
        return new RegimenSimplificadoVat(
            new VatPeriodResolver,
            new Modelo303CasillasMapper,
        );
    }

    /**
     * GOLDEN — 1T 2025 actividad bar (IAE 673.1).
     *
     * Módulos hipotéticos (a efectos didácticos del cálculo):
     *   - Personal asalariado: 1 persona × 1.200,00 €/año = 1.200,00 €
     *   - Superficie m²: 40 × 9,00 €/año = 360,00 €
     *   - Potencia kW: 8 × 1,80 €/año = 14,40 €
     *
     * Cuota anual módulos: 1.574,40 €.
     * Cuota 1T (× 0,25): 393,60 €.
     * IVA soportado en compras del 1T: 50,00 €.
     * Cuota líquida: 393,60 − 50,00 = 343,60 € → A_INGRESAR.
     */
    public function test_golden_simplificado_bar_1T_2025(): void
    {
        $compra = new VatTransactionInput(
            direction: VatTransactionDirection::INCOMING,
            date: CarbonImmutable::create(2025, 2, 15),
            base: new Money('500.00'),
            vatRate: TaxRate::fromPercentage('10.00'),
            vatAmount: new Money('50.00'),
            description: 'Compras bar 1T',
        );

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [$compra],
            quarter: 1,
            simplifiedModulesData: [
                'modules' => [
                    ['concept' => 'Personal asalariado', 'units' => '1', 'annual_quota_per_unit' => '1200.00'],
                    ['concept' => 'Superficie m2', 'units' => '40', 'annual_quota_per_unit' => '9.00'],
                    ['concept' => 'Potencia kW', 'units' => '8', 'annual_quota_per_unit' => '1.80'],
                ],
                'period_fraction' => '0.25',
                'activity_code' => '673.1',
            ],
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('393.60', $result->totalVatAccrued->amount);
        $this->assertSame('50.00', $result->totalVatDeductible->amount);
        $this->assertSame('343.60', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
        $this->assertSame('303', $result->model);

        // Casillas simplificado.
        $this->assertSame('1574.40', $result->casillas['80']->amount);
        $this->assertSame('393.60', $result->casillas['81']->amount);
        $this->assertSame('50.00', $result->casillas['82']->amount);
    }

    /**
     * GOLDEN — Anual simplificado: period_fraction 1.
     */
    public function test_golden_simplificado_anual(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [],
            quarter: null,
            simplifiedModulesData: [
                'modules' => [
                    ['concept' => 'Personal', 'units' => '2', 'annual_quota_per_unit' => '1200.00'],
                ],
                'period_fraction' => '1',
            ],
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('2400.00', $result->totalVatAccrued->amount);
        $this->assertSame('2400.00', $result->liquidQuota->amount);
        $this->assertSame('390', $result->model);
    }

    /**
     * Si IVA soportado supera la cuota módulos del período, queda a
     * compensar (período intermedio) o a devolver (último período).
     */
    public function test_simplificado_4T_a_devolver_cuando_soportado_supera_modulos(): void
    {
        $compra = new VatTransactionInput(
            direction: VatTransactionDirection::INCOMING,
            date: CarbonImmutable::create(2025, 11, 15),
            base: new Money('5000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('1050.00'),
            description: 'Compra grande',
        );

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [$compra],
            quarter: 4,
            simplifiedModulesData: [
                'modules' => [
                    ['concept' => 'Personal', 'units' => '1', 'annual_quota_per_unit' => '400.00'],
                ],
                'period_fraction' => '0.25',
            ],
        );

        $result = $this->calc()->calculate($input);

        // 1 × 400 × 0.25 = 100. 100 - 1050 = -950 → A_DEVOLVER (4T).
        $this->assertSame('-950.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_DEVOLVER, $result->result);
    }

    /**
     * Las transacciones outgoing en simplificado NO afectan al devengado
     * (lo fijan los módulos).
     */
    public function test_simplificado_outgoing_no_afecta_devengado(): void
    {
        $venta = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 2, 15),
            base: new Money('5000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('1050.00'),
            description: 'Venta bar',
        );

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_SIMPLE'),
            year: FiscalYear::fromInt(2025),
            transactions: [$venta],
            quarter: 1,
            simplifiedModulesData: [
                'modules' => [
                    ['concept' => 'Personal', 'units' => '1', 'annual_quota_per_unit' => '1200.00'],
                ],
                'period_fraction' => '0.25',
            ],
        );

        $result = $this->calc()->calculate($input);

        // Devengado = módulos × 0.25 = 300. (No afecta el outgoing).
        $this->assertSame('300.00', $result->totalVatAccrued->amount);
    }
}
