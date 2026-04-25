<?php

namespace Tests\Feature\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Vat\RegimenGeneralVat;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnStatus;
use Modules\Tax\DTOs\VatReturn\VatTransactionCategory;
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
 * Golden tests para RegimenGeneralVat (modelo 303 / 390 IVA general).
 *
 * Cada test cita Ley 37/1992 LIVA y/o Orden HFP/3666/2024 modelo 303.
 */
class RegimenGeneralVatTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): RegimenGeneralVat
    {
        return new RegimenGeneralVat(
            new VatPeriodResolver,
            new Modelo303CasillasMapper,
        );
    }

    private function outgoing(string $date, string $base, string $vat = '21.00', ?string $vatAmount = null): VatTransactionInput
    {
        $vatRate = TaxRate::fromPercentage($vat);
        $b = new Money($base);
        $amount = $vatAmount !== null ? new Money($vatAmount) : $b->applyRate($vatRate);

        return new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::parse($date),
            base: $b,
            vatRate: $vatRate,
            vatAmount: $amount,
            description: "Factura emitida {$date}",
        );
    }

    private function incoming(string $date, string $base, string $vat = '21.00', ?string $vatAmount = null): VatTransactionInput
    {
        $vatRate = TaxRate::fromPercentage($vat);
        $b = new Money($base);
        $amount = $vatAmount !== null ? new Money($vatAmount) : $b->applyRate($vatRate);

        return new VatTransactionInput(
            direction: VatTransactionDirection::INCOMING,
            date: CarbonImmutable::parse($date),
            base: $b,
            vatRate: $vatRate,
            vatAmount: $amount,
            description: "Factura recibida {$date}",
        );
    }

    /**
     * GOLDEN — Régimen general 2T 2025.
     *
     * 5 facturas emitidas (2.000 € cada una al 21 %) → IVA devengado 2.100,00 €.
     * 3 facturas recibidas (1.000 € cada una al 21 %) → IVA soportado 630,00 €.
     * Cuota líquida = 2.100 − 630 = 1.470,00 € → A_INGRESAR.
     *
     * Fuentes: art. 75 LIVA (devengo), art. 92 LIVA (deducción),
     * Orden HFP/3666/2024 modelo 303.
     */
    public function test_golden_regimen_general_2_t_2025_emitidas_y_recibidas(): void
    {
        $tx = [];
        for ($i = 0; $i < 5; $i++) {
            $tx[] = $this->outgoing('2025-04-15', '2000.00');
        }
        for ($i = 0; $i < 3; $i++) {
            $tx[] = $this->incoming('2025-05-10', '1000.00');
        }

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: $tx,
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('2100.00', $result->totalVatAccrued->amount);
        $this->assertSame('630.00', $result->totalVatDeductible->amount);
        $this->assertSame('1470.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
        $this->assertSame('303', $result->model);
        $this->assertSame('2T 2025', $result->period);

        // Casillas modelo 303.
        $this->assertSame('10000.00', $result->casillas['01']->amount);
        $this->assertSame('2100.00', $result->casillas['03']->amount);
        $this->assertSame('2100.00', $result->casillas['27']->amount);
        $this->assertSame('3000.00', $result->casillas['28']->amount);
        $this->assertSame('630.00', $result->casillas['29']->amount);
        $this->assertSame('630.00', $result->casillas['45']->amount);
        $this->assertSame('1470.00', $result->casillas['46']->amount);
        $this->assertSame('1470.00', $result->casillas['71']->amount);
    }

    /**
     * GOLDEN — Compensación períodos anteriores.
     *
     * IVA devengado 1000, soportado 200, previousQuotaCarryForward 500.
     * Diferencia = 1000 − 200 = 800. Cuota líquida = 800 − 500 = 300 €.
     * Resultado: A_INGRESAR.
     *
     * Fuente: art. 99 LIVA (compensación), Orden HFP/3666/2024 casilla 78.
     */
    public function test_golden_compensacion_periodos_anteriores(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-15', '4761.90', '21.00', '1000.00'), // 1000 IVA
                $this->incoming('2025-05-10', '952.38', '21.00', '200.00'), // 200 IVA
            ],
            quarter: 2,
            previousQuotaCarryForward: new Money('500.00'),
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('1000.00', $result->totalVatAccrued->amount);
        $this->assertSame('200.00', $result->totalVatDeductible->amount);
        $this->assertSame('300.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
        $this->assertSame('500.00', $result->casillas['78']->amount);
    }

    /**
     * GOLDEN — Resultado a devolver (anual).
     *
     * Cuota anual devengada 1000, soportada 1500. Cuota líquida = -500.
     * En modelo 390 anual → A_DEVOLVER, casilla 73 = 500.
     *
     * Fuente: art. 115 LIVA (devolución), Orden HFP/3666/2024 casilla 73.
     */
    public function test_golden_anual_a_devolver(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-03-10', '4761.90', '21.00', '1000.00'),
                $this->incoming('2025-08-15', '7142.86', '21.00', '1500.00'),
            ],
            quarter: null,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('1000.00', $result->totalVatAccrued->amount);
        $this->assertSame('1500.00', $result->totalVatDeductible->amount);
        $this->assertSame('-500.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_DEVOLVER, $result->result);
        $this->assertSame('390', $result->model);
        $this->assertArrayHasKey('73', $result->casillas);
        $this->assertSame('500.00', $result->casillas['73']->amount);
    }

    /**
     * GOLDEN — Resultado a compensar (1T intermedio).
     *
     * Cuota líquida negativa en 1T → A_COMPENSAR (no A_DEVOLVER).
     * Casilla 72 = importe absoluto.
     */
    public function test_golden_1_t_a_compensar(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-02-10', '476.19', '21.00', '100.00'),
                $this->incoming('2025-03-15', '1428.57', '21.00', '300.00'),
            ],
            quarter: 1,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('-200.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_COMPENSAR, $result->result);
        $this->assertArrayHasKey('72', $result->casillas);
        $this->assertArrayNotHasKey('73', $result->casillas);
        $this->assertSame('200.00', $result->casillas['72']->amount);
    }

    /**
     * GOLDEN — 390 anual agregando 4 trimestres equivalentes.
     *
     * Si en el año hay 4 facturas emitidas de 1000 € (una por trimestre)
     * y 4 recibidas de 200 € (una por trimestre), el modelo 390 anual
     * agrega los 4 trimestres → devengado 840, soportado 168, líquida 672.
     *
     * Fuente: Orden HAC/819/2024 modelo 390.
     */
    public function test_golden_390_anual_agregacion_4_trimestres(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-02-15', '1000.00'),
                $this->outgoing('2025-05-15', '1000.00'),
                $this->outgoing('2025-08-15', '1000.00'),
                $this->outgoing('2025-11-15', '1000.00'),
                $this->incoming('2025-02-20', '200.00'),
                $this->incoming('2025-05-20', '200.00'),
                $this->incoming('2025-08-20', '200.00'),
                $this->incoming('2025-11-20', '200.00'),
            ],
            quarter: null,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('840.00', $result->totalVatAccrued->amount);
        $this->assertSame('168.00', $result->totalVatDeductible->amount);
        $this->assertSame('672.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
        $this->assertSame('390', $result->model);
    }

    /**
     * Operaciones intracomunitarias se anotan informativas, no entran
     * en devengado ni deducible (fuera MVP M7).
     *
     * Fuente: art. 25 LIVA (entregas intracom).
     */
    public function test_intracom_es_informativa_no_calcula(): void
    {
        $intracom = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 5, 10),
            base: new Money('1000.00'),
            vatRate: TaxRate::fromPercentage('0.00'),
            vatAmount: new Money('0.00'),
            description: 'Cliente UE',
            category: VatTransactionCategory::INTRACOM,
        );

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-15', '1000.00'),
                $intracom,
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
    }

    /**
     * Recargo de equivalencia devengado se suma al ingreso a la AEAT.
     *
     * 1 factura emitida 1000 € + 21 % IVA + 5,2 % RE → IVA devengado 210,
     * RE 52, total ingreso 262.
     *
     * Fuente: art. 161 LIVA (Recargo de Equivalencia).
     */
    public function test_recargo_equivalencia_se_suma_al_ingreso(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('1000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('210.00'),
            description: 'Cliente RE',
            surchargeEquivalenceAmount: new Money('52.00'),
        );

        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [$tx],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('210.00', $result->totalVatAccrued->amount);
        $this->assertSame('52.00', $result->totalSurchargeEquivalenceAccrued->amount);
        // Cuota líquida = 210 (IVA) + 52 (RE) − 0 (deducible) − 0 (carry) = 262.
        $this->assertSame('262.00', $result->liquidQuota->amount);
        $this->assertArrayHasKey('19', $result->casillas);
        $this->assertArrayHasKey('21', $result->casillas);
    }

    /**
     * Múltiples tipos IVA se agrupan independientemente.
     */
    public function test_multiples_tipos_iva_se_agrupan_por_tipo(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-10', '1000.00', '21.00'), // 210
                $this->outgoing('2025-04-15', '500.00', '10.00'),  // 50
                $this->outgoing('2025-04-20', '200.00', '4.00'),   // 8
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('268.00', $result->totalVatAccrued->amount);
        $this->assertSame('1000.00', $result->casillas['01']->amount);
        $this->assertSame('210.00', $result->casillas['03']->amount);
        $this->assertSame('500.00', $result->casillas['04']->amount);
        $this->assertSame('50.00', $result->casillas['06']->amount);
        $this->assertSame('200.00', $result->casillas['07']->amount);
        $this->assertSame('8.00', $result->casillas['09']->amount);
    }

    /**
     * Transacciones fuera del trimestre no se incluyen.
     */
    public function test_transacciones_fuera_de_periodo_no_se_incluyen(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-15', '1000.00'), // 2T
                $this->outgoing('2025-07-15', '500.00'),  // 3T — fuera 2T
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        // Solo la primera (2T) entra → 210.
        $this->assertSame('210.00', $result->totalVatAccrued->amount);
    }

    /**
     * Quarterly liquidQuota = 0 → A_INGRESAR (formalmente "ingresar 0").
     */
    public function test_quota_liquida_cero_es_a_ingresar(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-15', '1000.00'), // 210
                $this->incoming('2025-05-10', '1000.00'), // 210
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);

        $this->assertSame('0.00', $result->liquidQuota->amount);
        $this->assertSame(VatReturnStatus::A_INGRESAR, $result->result);
    }

    /**
     * El breakdown contiene todas las líneas y referencias BOE.
     */
    public function test_breakdown_contiene_referencias_boe(): void
    {
        $input = new VatReturnInput(
            regime: RegimeCode::fromString('IVA_GEN'),
            year: FiscalYear::fromInt(2025),
            transactions: [
                $this->outgoing('2025-04-15', '1000.00'),
                $this->incoming('2025-05-10', '500.00'),
            ],
            quarter: 2,
        );

        $result = $this->calc()->calculate($input);
        $lines = $result->breakdown->lines;

        $this->assertNotEmpty($lines);
        $hasBoe = false;
        foreach ($lines as $line) {
            if ($line->legalReference !== null && str_contains((string) $line->legalReference, 'boe.es')) {
                $hasBoe = true;
                break;
            }
        }
        $this->assertTrue($hasBoe, 'El breakdown debe contener al menos una referencia a boe.es');
    }
}
