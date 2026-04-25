<?php

namespace Modules\Tax\DTOs\FractionalPayment;

use JsonSerializable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resultado de un pago fraccionado IRPF (modelos 130/131).
 *
 *  - breakdown: desglose paso-a-paso con BreakdownLine y referencia BOE en
 *    cada concepto.
 *  - model: '130' o '131'.
 *  - period: etiqueta del período (ej: '2T 2025').
 *  - cumulativeNetIncome: rendimiento neto acumulado (sólo modelo 130).
 *    Para modelo 131 se rellena con la cuota anual EO calculada por módulos.
 *  - applicableRate: tipo aplicable (modelo 130: 20 %; modelo 131: 4/3/2 %
 *    según asalariados).
 *  - grossPayment: pago bruto = base × tipo.
 *  - deductionDescendants: deducción por descendientes (100 € × n hijos
 *    convivientes y por trimestre, art. 110.3.c LIRPF).
 *  - withholdingsApplied: retenciones IRPF soportadas (art. 110.3.b LIRPF).
 *  - previousQuartersDeducted: pagos fraccionados del mismo año ya ingresados
 *    en trimestres anteriores (art. 110.3.a LIRPF).
 *  - result: importe a ingresar. Si el cálculo arroja un valor negativo, se
 *    establece a 0,00 — los modelos 130/131 NUNCA pueden ser a devolver
 *    (si hay exceso de retenciones / pagos previos, sólo se traslada al
 *    siguiente trimestre o se compensa en la Renta anual).
 *
 * Fuente: Ley 35/2006 art. 99-110 (BOE-A-2006-20764);
 * RD 439/2007 RIRPF arts. 105-110 (BOE-A-2007-6820);
 * Orden HFP/3666/2024 (BOE-A-2024-26173).
 */
final readonly class FractionalPaymentResult implements JsonSerializable
{
    public function __construct(
        public Breakdown $breakdown,
        public string $model,
        public string $period,
        public Money $cumulativeNetIncome,
        public TaxRate $applicableRate,
        public Money $grossPayment,
        public Money $deductionDescendants,
        public Money $withholdingsApplied,
        public Money $previousQuartersDeducted,
        public Money $result,
    ) {}

    public function isToPay(): bool
    {
        return ! $this->result->isZero();
    }

    /**
     * Indica si el resultado se ha forzado a 0 € por exceso de retenciones
     * o pagos previos. Útil en frontend para mostrar la tarjeta gris
     * "no procede pago" en lugar de la verde "a ingresar".
     */
    public function isClampedToZero(): bool
    {
        return $this->result->isZero();
    }

    public function jsonSerialize(): array
    {
        return [
            'breakdown' => $this->breakdown,
            'model' => $this->model,
            'period' => $this->period,
            'cumulative_net_income' => $this->cumulativeNetIncome,
            'applicable_rate' => $this->applicableRate,
            'gross_payment' => $this->grossPayment,
            'deduction_descendants' => $this->deductionDescendants,
            'withholdings_applied' => $this->withholdingsApplied,
            'previous_quarters_deducted' => $this->previousQuartersDeducted,
            'result' => $this->result,
            'is_to_pay' => $this->isToPay(),
            'is_clamped_to_zero' => $this->isClampedToZero(),
        ];
    }
}
