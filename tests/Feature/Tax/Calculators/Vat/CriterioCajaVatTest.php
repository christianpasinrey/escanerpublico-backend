<?php

namespace Tests\Feature\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Vat\CriterioCajaVat;
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
 * Golden tests para CriterioCajaVat (modelo 303 IVA Régimen Especial
 * Criterio de Caja).
 *
 * Fuente: art. 163 decies-sexiesdecies LIVA, Ley 14/2013.
 */
class CriterioCajaVatTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): CriterioCajaVat
    {
        return new CriterioCajaVat(
            new VatPeriodResolver,
            new Modelo303CasillasMapper,
        );
    }

    private function tx(string $direction, string $date, ?string $paidDate, string $base, string $vat = '21.00'): VatTransactionInput
    {
        $vatRate = TaxRate::fromPercentage($vat);
        $b = new Money($base);

        return new VatTransactionInput(
            direction: VatTransactionDirection::from($direction),
            date: CarbonImmutable::parse($date),
            base: $b,
            vatRate: $vatRate,
            vatAmount: $b->applyRate($vatRate),
            description: "Tx {$direction} {$date}",
            paidDate: $paidDate !== null ? CarbonImmutable::parse($paidDate) : null,
        );
    }

    /**
     * GOLDEN — Criterio caja Q1 2025 con factura emitida en marzo,
     * cobrada en abril.
     *
     * Factura emitida 1.000 € + 21 % IVA = 210 € repercutidos.
     * Fecha emisión: 2025-03-15. paidDate: 2025-04-10.
     *
     * En 1T 2025 (01-ene a 31-mar): NO se devenga (paidDate cae en 2T).
     * Resultado 1T: cuota 0 (no transactions devengadas).
     *
     * Fuente: art. 163 decies LIVA — el devengo se traslada al cobro.
     */
    public function test_golden_caja_factura_marzo_cobrada_abril_no_se_devenga_en_1T(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->tx('outgoing', '2025-03-15', '2025-04-10', '1000.00'),
            ],
            quarter: 1,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('0.00', $result->totalVatAccrued->amount);
        $this->assertSame('0.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
    }

    /**
     * GOLDEN — En 2T 2025 sí se devenga la factura del ejemplo anterior.
     */
    public function test_golden_caja_misma_factura_se_devenga_en_2T(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->tx('outgoing', '2025-03-15', '2025-04-10', '1000.00'),
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
        $this->assertSame('210.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
    }

    /**
     * Múltiples cobros en distintos trimestres.
     */
    public function test_caja_multiples_cobros_en_periodos_distintos(): void
    {
        $tx = [
            $this->tx('outgoing', '2025-01-15', '2025-02-15', '1000.00'),  // 1T
            $this->tx('outgoing', '2025-01-15', '2025-05-10', '1000.00'),  // 2T
            $this->tx('outgoing', '2025-02-15', '2025-09-15', '1000.00'),  // 3T
            $this->tx('outgoing', '2025-03-15', '2025-12-15', '1000.00'),  // 4T
        ];

        $r1 = $this->calc()->calculate(new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: $tx,
            quarter: 1,
        ));
        $this->assertSame('210.00', $r1->totalVatAccrued->amount);

        $r2 = $this->calc()->calculate(new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: $tx,
            quarter: 2,
        ));
        $this->assertSame('210.00', $r2->totalVatAccrued->amount);

        $r3 = $this->calc()->calculate(new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: $tx,
            quarter: 3,
        ));
        $this->assertSame('210.00', $r3->totalVatAccrued->amount);

        $r4 = $this->calc()->calculate(new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2025),
            transactions: $tx,
            quarter: 4,
        ));
        $this->assertSame('210.00', $r4->totalVatAccrued->amount);
    }

    /**
     * Edge: paidDate del año posterior pero antes del 31-dic siguiente
     * → devenga en el período del cobro.
     */
    public function test_caja_paid_date_ano_siguiente_devenga_en_ano_siguiente(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_CAJA'),
            year: FiscalYear::fromInt(2026),
            transactions: [
                $this->tx('outgoing', '2025-11-15', '2026-02-10', '1000.00'),
            ],
            quarter: 1,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
    }
}
