<?php

namespace Modules\Tax\DTOs\Invoice;

use JsonSerializable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\ValueObjects\Money;

/**
 * Resultado completo de una factura calculada.
 *
 * - breakdown: desglose línea a línea con categorías y referencias BOE.
 * - lines: detalle por cada InvoiceLineInput recibida.
 * - subtotal: suma de subtotales (sin IVA, sin RE, sin retenciones).
 * - totalVat: total IVA repercutido.
 * - totalSurchargeEquivalence: total recargo equivalencia (cliente RE).
 * - totalIrpfRetention: total retención IRPF (descuento al cobro).
 * - totalToCharge: importe efectivo a cobrar al cliente.
 *
 * Incluye disclaimer legal en `meta.disclaimer`.
 */
final readonly class InvoiceResult implements JsonSerializable
{
    /**
     * @param  list<InvoiceLineResult>  $lines
     */
    public function __construct(
        public Breakdown $breakdown,
        public array $lines,
        public Money $subtotal,
        public Money $totalVat,
        public Money $totalSurchargeEquivalence,
        public Money $totalIrpfRetention,
        public Money $totalToCharge,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'breakdown' => $this->breakdown,
            'lines' => $this->lines,
            'totals' => [
                'subtotal' => $this->subtotal,
                'total_vat' => $this->totalVat,
                'total_surcharge_equivalence' => $this->totalSurchargeEquivalence,
                'total_irpf_retention' => $this->totalIrpfRetention,
                'total_to_charge' => $this->totalToCharge,
            ],
        ];
    }
}
