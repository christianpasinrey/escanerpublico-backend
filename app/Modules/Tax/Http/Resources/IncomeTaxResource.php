<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxResult;
use Modules\Tax\ValueObjects\Money;

/**
 * Serializa el resultado del IRPF anual con disclaimer legal prominente,
 * breakdown completo línea a línea y totales agregados.
 *
 * @property-read IncomeTaxResult $resource
 */
class IncomeTaxResource extends JsonResource
{
    public const DISCLAIMER = 'Calculadora informativa del modelo 100 (IRPF anual). No incluye rendimientos del capital mobiliario, ganancias y pérdidas patrimoniales, pluriempleo, rendimientos irregulares, imputación de rentas inmobiliarias ni regímenes especiales (Beckham, foral PV/Navarra). Los importes pueden diferir de la declaración real por simplificaciones aplicadas. Consulte a un asesor fiscal antes de presentar la declaración.';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IncomeTaxResult $result */
        $result = $this->resource;

        return [
            'breakdown' => [
                'lines' => array_map(
                    fn (BreakdownLine $line) => $this->lineToArray($line),
                    $result->breakdown->lines,
                ),
                'summary' => array_map(
                    fn (Money $money) => $this->moneyToArray($money),
                    $result->breakdown->summary(),
                ),
                'meta' => $result->breakdown->meta,
            ],
            'totals' => [
                'net_income' => $this->moneyToArray($result->netIncome),
                'taxable_base' => $this->moneyToArray($result->taxableBase),
                'state_quota' => $this->moneyToArray($result->stateQuota),
                'regional_quota' => $this->moneyToArray($result->regionalQuota),
                'total_gross' => $this->moneyToArray($result->totalGross),
                'total_deductions' => $this->moneyToArray($result->totalDeductions),
                'liquid_quota' => $this->moneyToArray($result->liquidQuota),
                'quarterly_payments_applied' => $this->moneyToArray($result->quarterlyPaymentsApplied),
                'withholdings_applied' => $this->moneyToArray($result->withholdingsApplied),
                'effective_tax_rate' => [
                    'percentage' => (float) $result->effectiveTaxRate->percentage,
                ],
                'result' => $this->moneyToArray($result->result),
            ],
            'is_to_pay' => $result->isToPay(),
            'is_to_refund' => $result->isToRefund(),
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineToArray(BreakdownLine $line): array
    {
        return [
            'concept' => $line->concept,
            'category' => $line->category->value,
            'category_label' => $line->category->label(),
            'amount' => $this->moneyToArray($line->amount),
            'base' => $line->base !== null ? $this->moneyToArray($line->base) : null,
            'rate' => $line->rate !== null ? [
                'percentage' => (float) $line->rate->percentage,
            ] : null,
            'legal_reference' => $line->legalReference,
            'explanation' => $line->explanation,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function moneyToArray(Money $money): array
    {
        return [
            'amount' => $money->amount,
            'currency' => $money->currency,
        ];
    }
}
