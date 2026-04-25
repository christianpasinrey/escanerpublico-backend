<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentResult;

/**
 * Serializa el resultado de un pago fraccionado IRPF (modelos 130/131)
 * con disclaimer legal prominente, breakdown completo y totales planos.
 *
 * @property-read FractionalPaymentResult $resource
 */
class FractionalPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FractionalPaymentResult $result */
        $result = $this->resource;

        return [
            'breakdown' => $result->breakdown,
            'model' => $result->model,
            'period' => $result->period,
            'totals' => [
                'cumulative_net_income' => $result->cumulativeNetIncome,
                'applicable_rate' => $result->applicableRate,
                'gross_payment' => $result->grossPayment,
                'deduction_descendants' => $result->deductionDescendants,
                'withholdings_applied' => $result->withholdingsApplied,
                'previous_quarters_deducted' => $result->previousQuartersDeducted,
                'result' => $result->result,
            ],
            'is_to_pay' => $result->isToPay(),
            'is_clamped_to_zero' => $result->isClampedToZero(),
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 35/2006 LIRPF, RD 439/2007 RIRPF, Orden HFP/3666/2024 modelos 130/131). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT. Los modelos 130/131 nunca son a devolver: el resultado se trunca a 0,00 € si las deducciones superan el pago bruto.',
        ];
    }
}
