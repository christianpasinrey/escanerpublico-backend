<?php

namespace Modules\Tax\DTOs;

use JsonSerializable;
use Modules\Tax\ValueObjects\Money;

/**
 * Resultado completo de un cálculo. Lleva las líneas, la moneda y
 * un resumen agregado por categoría útil para presentar en frontend.
 */
final readonly class Breakdown implements JsonSerializable
{
    /**
     * @param  list<BreakdownLine>  $lines
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $lines,
        public Money $netResult,
        public string $currency = 'EUR',
        public array $meta = [],
    ) {}

    /**
     * Suma los importes filtrados por categoría.
     */
    public function totalByCategory(BreakdownCategory $category): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->lines as $line) {
            if ($line->category === $category) {
                $total = $total->add($line->amount);
            }
        }

        return $total;
    }

    /**
     * @return array<string, Money>
     */
    public function summary(): array
    {
        $summary = [];

        foreach (BreakdownCategory::cases() as $category) {
            $summary[$category->value] = $this->totalByCategory($category);
        }

        return $summary;
    }

    public function jsonSerialize(): array
    {
        return [
            'lines' => $this->lines,
            'summary' => $this->summary(),
            'net_result' => $this->netResult,
            'currency' => $this->currency,
            'meta' => $this->meta,
        ];
    }
}
