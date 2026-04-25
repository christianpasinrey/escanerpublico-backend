<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\DTOs\Invoice\InvoiceResult;

/**
 * Serializa el resultado de una factura calculada con disclaimer legal
 * prominente, breakdown completo y totales planos.
 *
 * @property-read InvoiceResult $resource
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InvoiceResult $result */
        $result = $this->resource;

        return [
            'breakdown' => $result->breakdown,
            'lines' => $result->lines,
            'totals' => [
                'subtotal' => $result->subtotal,
                'total_vat' => $result->totalVat,
                'total_surcharge_equivalence' => $result->totalSurchargeEquivalence,
                'total_irpf_retention' => $result->totalIrpfRetention,
                'total_to_charge' => $result->totalToCharge,
            ],
            'disclaimer' => 'Cálculo informativo. No sustituye al asesoramiento profesional ni a la presentación oficial AEAT. Verifique cada importe con su asesor antes de emitir la factura.',
        ];
    }
}
