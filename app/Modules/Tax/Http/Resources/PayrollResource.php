<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\Payroll\PayrollResult;
use Modules\Tax\ValueObjects\Money;

/**
 * @mixin PayrollResult
 */
class PayrollResource extends JsonResource
{
    public const DISCLAIMER = 'Esta calculadora es informativa y no constituye asesoramiento fiscal. La nómina real depende del convenio colectivo, complementos, antigüedad, dietas, horas extra y otras circunstancias que esta calculadora simplifica. Consulte a un asesor fiscal para casos concretos.';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollResult $result */
        $result = $this->resource;

        return [
            'monthly_gross' => $this->moneyToArray($result->monthlyGross),
            'monthly_net' => $this->moneyToArray($result->monthlyNet),
            'annual_gross' => $this->moneyToArray($result->annualGross),
            'annual_net' => $this->moneyToArray($result->annualNet),
            'effective_tax_rate' => [
                'percentage' => (float) $result->effectiveTaxRate->percentage,
            ],
            'company_total_cost' => $this->moneyToArray($result->companyTotalCost),
            'breakdown' => [
                'lines' => array_map(fn (BreakdownLine $line) => $this->lineToArray($line), $result->breakdown->lines),
                'summary' => array_map(
                    fn ($money) => $this->moneyToArray($money),
                    $result->breakdown->summary(),
                ),
                'meta' => $result->breakdown->meta,
            ],
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
