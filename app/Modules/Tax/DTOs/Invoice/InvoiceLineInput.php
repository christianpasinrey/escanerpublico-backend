<?php

namespace Modules\Tax\DTOs\Invoice;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\Money;

/**
 * Input de una línea de factura. Importes en bcmath (Money), nunca floats.
 * El tipo de IVA aplicable se indica como categoría (general/reducido/...)
 * y el rate concreto se resuelve internamente con vat_product_rates +
 * activity_code o keyword del emisor cuando aplique.
 *
 * Cantidad acepta tanto entero como string decimal (ej: "1.5") para soportar
 * facturación por horas, kilos, metros, etc.
 */
final readonly class InvoiceLineInput implements JsonSerializable
{
    public function __construct(
        public string $description,
        public string $quantity,
        public Money $unitPrice,
        public VatRateType $vatRateType = VatRateType::GENERAL,
        public bool $irpfRetentionApplies = true,
    ) {
        if ($description === '') {
            throw new InvalidArgumentException('La descripción de la línea no puede estar vacía.');
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $quantity)) {
            throw new InvalidArgumentException("Cantidad inválida: {$quantity}");
        }

        if (bccomp($quantity, '0', 8) <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        if ($unitPrice->isNegative()) {
            throw new InvalidArgumentException('El precio unitario no puede ser negativo.');
        }
    }

    /**
     * Subtotal pre-IVA: cantidad × precio unitario, escala 2 decimales (céntimos).
     */
    public function subtotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function jsonSerialize(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'vat_rate_type' => $this->vatRateType->value,
            'irpf_retention_applies' => $this->irpfRetentionApplies,
        ];
    }
}
