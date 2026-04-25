<?php

namespace Modules\Tax\DTOs\VatReturn;

use JsonSerializable;
use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\ValueObjects\Money;

/**
 * Resultado de una autoliquidación de IVA (modelos 303 / 390).
 *
 *  - breakdown: desglose paso-a-paso, con BreakdownLine por concepto y
 *    referencia BOE en cada línea.
 *  - model: '303' (trimestral) o '390' (anual).
 *  - period: etiqueta humana del período (ej: "2T 2025", "Anual 2025").
 *  - totalVatAccrued: total IVA devengado (repercutido por ventas).
 *  - totalVatDeductible: total IVA soportado deducible.
 *  - totalSurchargeEquivalenceAccrued: total recargo equivalencia
 *    devengado (cliente RE). En modelo 303 se declara junto al IVA pero
 *    como concepto separado (casillas 16, 18, 20, 22, 24, 26).
 *  - liquidQuota: cuota líquida (devengado − deducible − compensaciones).
 *    Positiva = a ingresar; negativa = a compensar/devolver.
 *  - result: a_ingresar | a_compensar | a_devolver.
 *  - casillas: mapeo de las casillas del modelo 303 cubiertas por este
 *    cálculo, con su valor en Money. Las casillas no cubiertas no están
 *    presentes en el array (no se introduce 0 en casillas no aplicables).
 *
 * Fuente: Orden HFP/3666/2024 modelo 303 vigente desde 2025; Orden
 * HAC/819/2024 modelo 390 anual.
 */
final readonly class VatReturnResult implements JsonSerializable
{
    /**
     * @param  array<string, Money>  $casillas  clave casilla del modelo 303 → valor
     */
    public function __construct(
        public Breakdown $breakdown,
        public string $model,
        public string $period,
        public Money $totalVatAccrued,
        public Money $totalVatDeductible,
        public Money $totalSurchargeEquivalenceAccrued,
        public Money $liquidQuota,
        public VatReturnStatus $result,
        public array $casillas,
    ) {}

    public function jsonSerialize(): array
    {
        // Casillas como lista ordenada de {key, value} para preservar
        // el orden y las claves '01', '03'… frente a normalizaciones.
        $casillas = [];
        foreach ($this->casillas as $key => $money) {
            $casillas[] = [
                'key' => (string) $key,
                'value' => $money,
            ];
        }

        return [
            'breakdown' => $this->breakdown,
            'model' => $this->model,
            'period' => $this->period,
            'totals' => [
                'total_vat_accrued' => $this->totalVatAccrued,
                'total_vat_deductible' => $this->totalVatDeductible,
                'total_surcharge_equivalence_accrued' => $this->totalSurchargeEquivalenceAccrued,
                'liquid_quota' => $this->liquidQuota,
            ],
            'result' => $this->result->value,
            'result_label' => $this->result->label(),
            'casillas' => $casillas,
        ];
    }
}
