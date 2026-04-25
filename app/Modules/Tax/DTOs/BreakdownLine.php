<?php

namespace Modules\Tax\DTOs;

use JsonSerializable;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Una línea del desglose de un cálculo fiscal. Cada línea explica
 * un concepto, su base, su porcentaje aplicado, su importe y la
 * referencia legal en el BOE para que el ciudadano pueda auditar
 * exactamente por qué paga lo que paga.
 */
final readonly class BreakdownLine implements JsonSerializable
{
    public function __construct(
        public string $concept,
        public Money $amount,
        public BreakdownCategory $category,
        public ?Money $base = null,
        public ?TaxRate $rate = null,
        public ?string $legalReference = null,
        public ?string $explanation = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'concept' => $this->concept,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'base' => $this->base,
            'rate' => $this->rate,
            'amount' => $this->amount,
            'legal_reference' => $this->legalReference,
            'explanation' => $this->explanation,
        ];
    }
}
