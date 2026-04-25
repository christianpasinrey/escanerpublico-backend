<?php

namespace Modules\Tax\DTOs\IncomeTax;

use JsonSerializable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resultado de la calculadora de IRPF anual (modelo 100).
 *
 * Inmutable. Contiene el desglose línea a línea (Breakdown) más resúmenes
 * de alto nivel para presentar en el frontend (cuotas, base, deducciones,
 * pagos a cuenta, resultado).
 *
 * Convenio de signos:
 *  - result > 0 → cuota a ingresar (a pagar Hacienda).
 *  - result < 0 → cuota a devolver (Hacienda devuelve al contribuyente).
 *  - result = 0 → declaración a "cuota cero".
 */
final readonly class IncomeTaxResult implements JsonSerializable
{
    public function __construct(
        public Breakdown $breakdown,
        public Money $netIncome,
        public Money $taxableBase,
        public Money $stateQuota,
        public Money $regionalQuota,
        public Money $totalGross,
        public Money $totalDeductions,
        public Money $liquidQuota,
        public Money $quarterlyPaymentsApplied,
        public Money $withholdingsApplied,
        public TaxRate $effectiveTaxRate,
        public Money $result,
    ) {}

    public function isToPay(): bool
    {
        return ! $this->result->isNegative() && ! $this->result->isZero();
    }

    public function isToRefund(): bool
    {
        return $this->result->isNegative();
    }

    public function jsonSerialize(): array
    {
        return [
            'breakdown' => $this->breakdown,
            'net_income' => $this->netIncome,
            'taxable_base' => $this->taxableBase,
            'state_quota' => $this->stateQuota,
            'regional_quota' => $this->regionalQuota,
            'total_gross' => $this->totalGross,
            'total_deductions' => $this->totalDeductions,
            'liquid_quota' => $this->liquidQuota,
            'quarterly_payments_applied' => $this->quarterlyPaymentsApplied,
            'withholdings_applied' => $this->withholdingsApplied,
            'effective_tax_rate' => $this->effectiveTaxRate,
            'result' => $this->result,
            'is_to_pay' => $this->isToPay(),
            'is_to_refund' => $this->isToRefund(),
        ];
    }
}
