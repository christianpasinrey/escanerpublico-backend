<?php

namespace Modules\Tax\DTOs\Payroll;

use JsonSerializable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resultado de la calculadora de nómina (régimen general).
 *
 * Inmutable. Contiene el desglose línea a línea (Breakdown) más resúmenes
 * de alto nivel para presentar en el frontend (bruto/neto mensual y anual,
 * tipo efectivo, coste empresa).
 */
final readonly class PayrollResult implements JsonSerializable
{
    public function __construct(
        public Breakdown $breakdown,
        public Money $monthlyGross,
        public Money $monthlyNet,
        public Money $annualGross,
        public Money $annualNet,
        public TaxRate $effectiveTaxRate,
        public Money $companyTotalCost,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'breakdown' => $this->breakdown,
            'monthly_gross' => $this->monthlyGross,
            'monthly_net' => $this->monthlyNet,
            'annual_gross' => $this->annualGross,
            'annual_net' => $this->annualNet,
            'effective_tax_rate' => $this->effectiveTaxRate,
            'company_total_cost' => $this->companyTotalCost,
        ];
    }
}
