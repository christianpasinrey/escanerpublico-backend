<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;

/**
 * Serializa el resultado de una autoliquidación de IVA con disclaimer
 * legal prominente, breakdown completo, totales planos, casillas del
 * modelo 303 y resultado.
 *
 * @property-read VatReturnResult $resource
 */
class VatReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VatReturnResult $result */
        $result = $this->resource;

        return [
            'breakdown' => $result->breakdown,
            'model' => $result->model,
            'period' => $result->period,
            'totals' => [
                'total_vat_accrued' => $result->totalVatAccrued,
                'total_vat_deductible' => $result->totalVatDeductible,
                'total_surcharge_equivalence_accrued' => $result->totalSurchargeEquivalenceAccrued,
                'liquid_quota' => $result->liquidQuota,
            ],
            'result' => $result->result->value,
            'result_label' => $result->result->label(),
            'casillas' => $result->casillas,
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 37/1992 LIVA, RD 1624/1992 RIVA, Orden HFP/3666/2024 modelo 303, Orden HAC/819/2024 modelo 390). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT. Verifique cada importe con su asesor antes de presentar el modelo.',
        ];
    }
}
