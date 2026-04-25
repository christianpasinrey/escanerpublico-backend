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

        // Las casillas del modelo 303 deben mantener sus claves ('01',
        // '03', '27', '45'…) tal cual el orden oficial. Como PHP convierte
        // las claves numéricas-string a int en algunos casos y Laravel
        // JsonResource puede normalizarlas a list al recursar, exponemos
        // las casillas como un array de objetos {key, value} ordenado.
        // El frontend reconstruye el mapeo si lo necesita.
        $casillas = [];
        foreach ($result->casillas as $key => $money) {
            $casillas[] = [
                'key' => (string) $key,
                'value' => $money->jsonSerialize(),
            ];
        }

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
            'casillas' => $casillas,
            'disclaimer' => 'Cálculo informativo basado en la legislación vigente (Ley 37/1992 LIVA, RD 1624/1992 RIVA, Orden HFP/3666/2024 modelo 303, Orden HAC/819/2024 modelo 390). No sustituye al asesoramiento profesional ni a la presentación oficial AEAT. Verifique cada importe con su asesor antes de presentar el modelo.',
        ];
    }
}
