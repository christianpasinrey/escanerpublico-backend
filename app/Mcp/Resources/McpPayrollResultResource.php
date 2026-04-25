<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\Payroll\PayrollResult;

/**
 * Resultado del cálculo de nómina. Serializa el desglose línea a línea
 * (lines + summary + net_result) más los totales mensuales/anuales y
 * el coste empresa.
 *
 * No es un Eloquent model — es un DTO inmutable de la calculadora.
 */
class McpPayrollResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollResult $result */
        $result = $this->resource;

        $breakdown = $result->breakdown;
        $summary = [];
        foreach ($breakdown->summary() as $cat => $money) {
            $summary[$cat] = [
                'amount' => (float) $money->amount,
                'currency' => $money->currency,
            ];
        }

        $lines = [];
        foreach ($breakdown->lines as $line) {
            $lines[] = [
                'concept' => $line->concept,
                'category' => $line->category->value,
                'category_label' => $line->category->label(),
                'amount' => (float) $line->amount->amount,
                'currency' => $line->amount->currency,
                'rate_percentage' => $line->rate !== null ? (float) $line->rate->percentage : null,
                'base' => $line->base !== null ? (float) $line->base->amount : null,
                'legal_reference' => $line->legalReference,
                'explanation' => $line->explanation,
            ];
        }

        return [
            'breakdown' => [
                'lines' => $lines,
                'summary' => $summary,
                'net_result' => [
                    'amount' => (float) $breakdown->netResult->amount,
                    'currency' => $breakdown->netResult->currency,
                ],
                'currency' => $breakdown->currency,
                'meta' => $breakdown->meta,
            ],
            'monthly_gross' => (float) $result->monthlyGross->amount,
            'monthly_net' => (float) $result->monthlyNet->amount,
            'annual_gross' => (float) $result->annualGross->amount,
            'annual_net' => (float) $result->annualNet->amount,
            'effective_tax_rate' => (float) (string) $result->effectiveTaxRate,
            'company_total_cost' => (float) $result->companyTotalCost->amount,
            'currency' => $breakdown->currency,
        ];
    }
}
