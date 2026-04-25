<?php

namespace Modules\Tax\Calculators\Vat;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;
use Modules\Tax\DTOs\VatReturn\VatReturnStatus;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\ValueObjects\Money;

/**
 * Calculator IVA modelo 303 — Régimen Simplificado (IVA_SIMPLE).
 *
 * El régimen simplificado del IVA (art. 122-123 LIVA, RD 1624/1992 RIVA
 * cap. III) tributa la actividad mediante MÓDULOS:
 *
 *   Cuota anual devengada = Σ (módulo_i × cuota_anual_módulo_i × % ocupación)
 *
 * Ejemplo (bar/cafetería 2025, IAE 673.1 — Anexo II Orden anual de
 * módulos):
 *   - Personal asalariado: 1 200,00 €/persona/año
 *   - Superficie del local: 9,00 €/m²/año
 *   - Potencia eléctrica: 1,80 €/kW/año
 *
 * En autoliquidaciones trimestrales se ingresa una FRACCIÓN (típicamente
 * 25 % de la cuota anual derivada de módulos en 1T-3T, y 4T regulariza).
 * Para MVP M7 implementamos el cálculo simple:
 *
 *   cuota_modulos_periodo = cuota_anual_modulos × fracción_periodo
 *   cuota_a_ingresar = cuota_modulos_periodo − IVA_soportado_deducible
 *
 * Las transacciones OUTGOING (ventas) NO se procesan: la cuota devengada
 * la fijan los módulos. Las INCOMING sí (sólo % autoaplicado).
 *
 * Estructura de simplifiedModulesData:
 *   [
 *     'modules' => [
 *       ['concept' => 'Personal asalariado', 'units' => '1.5',
 *        'annual_quota_per_unit' => '1200.00', 'note' => '...'],
 *       ['concept' => 'Superficie m2', 'units' => '40',
 *        'annual_quota_per_unit' => '9.00'],
 *       ...
 *     ],
 *     'period_fraction' => '0.25',  // 1/4 si trimestral, 1 si anual
 *     'activity_code' => '673.1',
 *   ]
 *
 * Fuentes BOE:
 *  - Ley 37/1992 LIVA, arts. 122-123.
 *  - RD 1624/1992 RIVA, arts. 34-43.
 *  - Orden HAC/1167/2024 (módulos 2025) o equivalente vigente — Anexo II.
 *
 * Nota MVP M7: si el usuario no introduce los módulos del Anexo II
 * vigente, el cálculo es solo la suma directa de lo que envía. NO
 * inventamos parámetros: si faltan, el calculator los usa tal cual.
 */
class RegimenSimplificadoVat implements VatRegimeReturnCalculator
{
    private const LIVA_122 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a122';

    private const RIVA_34 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-29059#a34';

    private const ORDEN_303_2024 = 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26173';

    public function __construct(
        private readonly VatPeriodResolver $periodResolver,
        private readonly Modelo303CasillasMapper $casillasMapper,
    ) {}

    public function calculate(VatReturnInput $input): VatReturnResult
    {
        $period = $this->periodResolver->resolve($input->year, $input->quarter);

        // 1. Calcular cuota devengada por módulos.
        $modulesData = $input->simplifiedModulesData ?? [];
        $modules = $modulesData['modules'] ?? [];
        $periodFraction = (string) ($modulesData['period_fraction'] ?? ($input->isAnnual() ? '1' : '0.25'));

        $cuotaAnualModulos = Money::zero();
        $moduleLines = [];
        foreach ($modules as $idx => $module) {
            $concept = (string) ($module['concept'] ?? "Módulo #{$idx}");
            $units = (string) ($module['units'] ?? '0');
            $annualPerUnit = (string) ($module['annual_quota_per_unit'] ?? '0.00');

            $moduleQuota = (new Money($annualPerUnit))->multiply($units);
            $cuotaAnualModulos = $cuotaAnualModulos->add($moduleQuota);

            $moduleLines[] = new BreakdownLine(
                concept: "Módulo: {$concept}",
                amount: $moduleQuota,
                category: BreakdownCategory::TAX,
                base: new Money($annualPerUnit),
                legalReference: self::RIVA_34,
                explanation: "Cuota anual = {$units} unidades × {$annualPerUnit} EUR/unidad (Anexo II Orden anual de módulos).",
            );
        }

        $cuotaModulosPeriodo = $cuotaAnualModulos->multiply($periodFraction);

        // 2. IVA soportado deducible: incoming dentro del período.
        $domesticIncoming = [];
        foreach ($input->transactions as $tx) {
            /** @var VatTransactionInput $tx */
            if (! $period->contains($tx->date)) {
                continue;
            }
            if ($tx->direction !== VatTransactionDirection::INCOMING) {
                continue;
            }
            $domesticIncoming[] = $tx;
        }

        $deductibleBuckets = [];
        $totalDeductibleBase = Money::zero();
        $totalDeductibleAmount = Money::zero();
        foreach ($domesticIncoming as $tx) {
            $key = $tx->vatRate->percentage;
            if (! isset($deductibleBuckets[$key])) {
                $deductibleBuckets[$key] = ['base' => Money::zero(), 'vat' => Money::zero()];
            }
            $deductibleBuckets[$key]['base'] = $deductibleBuckets[$key]['base']->add($tx->base);
            $deductibleBuckets[$key]['vat'] = $deductibleBuckets[$key]['vat']->add($tx->vatAmount);
            $totalDeductibleBase = $totalDeductibleBase->add($tx->base);
            $totalDeductibleAmount = $totalDeductibleAmount->add($tx->vatAmount);
        }

        $diferencia = $cuotaModulosPeriodo->subtract($totalDeductibleAmount);
        $liquidQuota = $diferencia->subtract($input->previousQuotaCarryForward);

        $isLastPeriod = $this->periodResolver->isLastPeriod($input->quarter);
        $status = $this->resolveStatus($liquidQuota, $isLastPeriod);

        $lines = [];

        // Encabezado: cuota anual módulos.
        foreach ($moduleLines as $ml) {
            $lines[] = $ml;
        }

        $lines[] = new BreakdownLine(
            concept: 'Cuota anual devengada por módulos',
            amount: $cuotaAnualModulos,
            category: BreakdownCategory::TAX,
            legalReference: self::LIVA_122,
            explanation: 'Suma de las cuotas anuales aplicables a cada módulo (art. 122 LIVA, art. 34 RIVA).',
        );

        $lines[] = new BreakdownLine(
            concept: 'Cuota devengada del período (× '.$periodFraction.')',
            amount: $cuotaModulosPeriodo,
            category: BreakdownCategory::TAX,
            legalReference: self::ORDEN_303_2024,
            explanation: 'Fracción del período aplicada a la cuota anual. Trimestral típicamente 25 %, anual 100 %.',
        );

        foreach ($deductibleBuckets as $rate => $bucket) {
            $rateLabel = $this->formatPercent($rate);
            $lines[] = new BreakdownLine(
                concept: "IVA soportado deducible al {$rateLabel}%",
                amount: $bucket['vat'],
                category: BreakdownCategory::DEDUCTION,
                base: $bucket['base'],
                legalReference: self::LIVA_122,
                explanation: 'IVA soportado en compras y servicios afectos a la actividad, deducible en simplificado (sólo % autoaplicado en MVP).',
            );
        }

        if (! $totalDeductibleAmount->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Total IVA soportado deducible',
                amount: $totalDeductibleAmount,
                category: BreakdownCategory::DEDUCTION,
                explanation: 'Total a deducir de la cuota devengada por módulos.',
            );
        }

        if (! $input->previousQuotaCarryForward->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Compensación de cuotas a compensar de períodos anteriores',
                amount: $input->previousQuotaCarryForward,
                category: BreakdownCategory::DEDUCTION,
                legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740#a99',
                explanation: 'Cuotas pendientes de compensar (art. 99 LIVA).',
            );
        }

        $lines[] = new BreakdownLine(
            concept: 'Cuota líquida — '.$status->label(),
            amount: $liquidQuota,
            category: BreakdownCategory::NET,
            legalReference: self::ORDEN_303_2024,
            explanation: $this->resultExplanation($status),
        );

        $coverage = $this->casillasMapper->coverage();
        $meta = [
            'regime' => $input->regime->code,
            'fiscal_year' => $input->year->year,
            'quarter' => $input->quarter,
            'model' => $input->model(),
            'period_label' => $input->periodLabel(),
            'simplified_modules_count' => count($modules),
            'period_fraction' => $periodFraction,
            'covered_casillas' => $coverage['covered'],
            'uncovered_casillas' => $coverage['uncovered'],
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 37/1992 LIVA arts. 122-123, RD 1624/1992 RIVA arts. 34-43). Los importes de los módulos deben tomarse del Anexo II de la Orden anual vigente. No sustituye al asesoramiento profesional ni a la presentación oficial AEAT.',
        ];

        $breakdown = new Breakdown(
            lines: $lines,
            netResult: $liquidQuota,
            currency: 'EUR',
            meta: $meta,
        );

        // Casillas: en simplificado se usa una sección distinta (80-88).
        // En MVP exponemos el cálculo principal pero no el mapping completo
        // de casillas simplificadas (queda fuera de coverage).
        $casillas = [
            // Resumen del cálculo simplificado:
            '80' => $cuotaAnualModulos,
            '81' => $cuotaModulosPeriodo,
            '82' => $totalDeductibleAmount,
            '83' => $diferencia,
        ];

        if (! $input->previousQuotaCarryForward->isZero()) {
            $casillas['78'] = $input->previousQuotaCarryForward;
        }

        $casillas['71'] = $liquidQuota->isNegative() ? Money::zero() : $liquidQuota;

        if ($liquidQuota->isNegative()) {
            $absolute = new Money(ltrim($liquidQuota->amount, '-'), $liquidQuota->currency);
            if ($status === VatReturnStatus::A_DEVOLVER) {
                $casillas['73'] = $absolute;
            } else {
                $casillas['72'] = $absolute;
            }
        }

        return new VatReturnResult(
            breakdown: $breakdown,
            model: $input->model(),
            period: $input->periodLabel(),
            totalVatAccrued: $cuotaModulosPeriodo,
            totalVatDeductible: $totalDeductibleAmount,
            totalSurchargeEquivalenceAccrued: Money::zero(),
            liquidQuota: $liquidQuota,
            result: $status,
            casillas: $casillas,
        );
    }

    private function resolveStatus(Money $liquidQuota, bool $isLastPeriod): VatReturnStatus
    {
        if (! $liquidQuota->isNegative() && ! $liquidQuota->isZero()) {
            return VatReturnStatus::A_INGRESAR;
        }

        if ($liquidQuota->isZero()) {
            return VatReturnStatus::A_INGRESAR;
        }

        if ($isLastPeriod) {
            return VatReturnStatus::A_DEVOLVER;
        }

        return VatReturnStatus::A_COMPENSAR;
    }

    private function resultExplanation(VatReturnStatus $status): string
    {
        return match ($status) {
            VatReturnStatus::A_INGRESAR => 'Cuota líquida positiva: el contribuyente debe ingresar el importe a la AEAT.',
            VatReturnStatus::A_COMPENSAR => 'Cuota líquida negativa en período intermedio: se traslada como cuota a compensar.',
            VatReturnStatus::A_DEVOLVER => 'Cuota líquida negativa en último período: se solicita devolución.',
        };
    }

    private function formatPercent(string $percentage): string
    {
        $trimmed = rtrim(rtrim($percentage, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
