<?php

namespace Modules\Tax\DTOs\Invoice;

use JsonSerializable;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resultado calculado de una línea de factura. Importes en bcmath.
 *
 * - subtotal = quantity * unitPrice (sin IVA)
 * - vatBase = base sobre la que se aplica IVA (igual a subtotal salvo
 *   excepciones futuras tipo descuentos por línea)
 * - vatAmount = vatBase * vatRate
 * - irpfRetentionBase = subtotal si la línea está sujeta a retención,
 *   0 en caso contrario
 * - irpfRetentionAmount = irpfRetentionBase * irpfRetentionRate
 *   (siempre proporcional a la base de la línea, no global)
 */
final readonly class InvoiceLineResult implements JsonSerializable
{
    public function __construct(
        public string $description,
        public string $quantity,
        public Money $unitPrice,
        public Money $subtotal,
        public Money $vatBase,
        public TaxRate $vatRate,
        public Money $vatAmount,
        public VatRateType $vatRateType,
        public Money $irpfRetentionBase,
        public TaxRate $irpfRetentionRate,
        public Money $irpfRetentionAmount,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal,
            'vat_base' => $this->vatBase,
            'vat_rate' => $this->vatRate,
            'vat_rate_type' => $this->vatRateType->value,
            'vat_amount' => $this->vatAmount,
            'irpf_retention_base' => $this->irpfRetentionBase,
            'irpf_retention_rate' => $this->irpfRetentionRate,
            'irpf_retention_amount' => $this->irpfRetentionAmount,
        ];
    }
}
