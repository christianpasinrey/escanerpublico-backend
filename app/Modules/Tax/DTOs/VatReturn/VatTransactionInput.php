<?php

namespace Modules\Tax\DTOs\VatReturn;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Una operación (factura emitida o recibida) que entra en la autoliquidación
 * de IVA del período. Inmutable.
 *
 * - direction: outgoing (emitida) o incoming (recibida).
 * - date: fecha de devengo según regla general (art. 75 LIVA: emisión).
 * - paidDate: fecha de cobro/pago efectivo. Solo es relevante en régimen
 *   de criterio de caja (IVA_CAJA), donde el devengo se traslada al cobro
 *   (art. 163 decies LIVA) con tope 31-dic del año posterior.
 * - base: importe sin IVA.
 * - vatRate: tipo aplicado al base (TaxRate, en %).
 * - vatAmount: cuota soportada o repercutida. Se recibe pre-calculado por
 *   robustez (la fuente puede ser una factura oficial); el calculator
 *   verificará coherencia con `base × vatRate` con tolerancia mínima.
 * - surchargeEquivalenceAmount: cuota de Recargo de Equivalencia (sólo en
 *   facturas emitidas a clientes acogidos a RE — art. 161 LIVA).
 * - category: clasificación informativa (interior/intracom/import/export).
 * - description: texto humano (no PII en MVP).
 * - clientOrSupplierNif: NIF de la contraparte (opcional — informativo).
 *
 * Reglas de validación de dominio (en constructor):
 *   - base no negativa, vatAmount no negativo.
 *   - surchargeEquivalenceAmount, si presente, no negativo.
 *   - paidDate, si presente, >= date (no se puede cobrar antes de emitir).
 *   - vatAmount debe coincidir con base × vatRate ± 0.01 € (céntimo de
 *     redondeo por línea). Si no coincide, lanza InvalidArgumentException.
 *
 * Fuente: Ley 37/1992 LIVA — arts. 75 (devengo general), 78 (base imponible),
 * 90-91 (tipos), 154-163 (Recargo de Equivalencia), 163 decies-163 sexiesdecies
 * (Régimen Especial Criterio de Caja).
 */
final readonly class VatTransactionInput implements JsonSerializable
{
    public function __construct(
        public VatTransactionDirection $direction,
        public CarbonImmutable $date,
        public Money $base,
        public TaxRate $vatRate,
        public Money $vatAmount,
        public string $description,
        public VatTransactionCategory $category = VatTransactionCategory::DOMESTIC,
        public ?CarbonImmutable $paidDate = null,
        public ?Money $surchargeEquivalenceAmount = null,
        public ?Nif $clientOrSupplierNif = null,
    ) {
        if ($description === '') {
            throw new InvalidArgumentException('La descripción de la transacción no puede estar vacía.');
        }

        if ($base->isNegative()) {
            throw new InvalidArgumentException('La base imponible no puede ser negativa.');
        }

        if ($vatAmount->isNegative()) {
            throw new InvalidArgumentException('La cuota de IVA no puede ser negativa.');
        }

        if ($surchargeEquivalenceAmount !== null && $surchargeEquivalenceAmount->isNegative()) {
            throw new InvalidArgumentException('El recargo de equivalencia no puede ser negativo.');
        }

        if ($paidDate !== null && $paidDate->lessThan($date)) {
            throw new InvalidArgumentException(
                'La fecha de cobro/pago no puede ser anterior a la fecha de devengo.'
            );
        }

        // Coherencia: vatAmount ≈ base × vatRate ± 0.01 € (tolerancia céntimo).
        $expected = $base->applyRate($vatRate);
        $diff = bcsub($vatAmount->amount, $expected->amount, Money::SCALE);
        $absDiff = ltrim($diff, '-');
        if (bccomp($absDiff, '0.01', Money::SCALE) > 0) {
            throw new InvalidArgumentException(
                "Cuota de IVA {$vatAmount->amount} no coherente con base {$base->amount} × tipo {$vatRate->percentage}% (esperado {$expected->amount}, diferencia {$diff})."
            );
        }
    }

    /**
     * Indica si la operación se ha cobrado/pagado a una fecha dada.
     * Útil para criterio de caja.
     */
    public function isPaidBy(CarbonImmutable $cutoff): bool
    {
        return $this->paidDate !== null && ! $this->paidDate->greaterThan($cutoff);
    }

    /**
     * Devenga forzoso a 31-dic del año posterior al devengo, regla del
     * art. 163 terdecies LIVA: si pasado ese plazo no se ha cobrado,
     * se considera devengado igualmente.
     */
    public function forcedAccrualDate(): CarbonImmutable
    {
        return CarbonImmutable::create($this->date->year + 1, 12, 31, 23, 59, 59);
    }

    public function jsonSerialize(): array
    {
        return [
            'direction' => $this->direction->value,
            'date' => $this->date->toDateString(),
            'paid_date' => $this->paidDate?->toDateString(),
            'base' => $this->base,
            'vat_rate' => $this->vatRate,
            'vat_amount' => $this->vatAmount,
            'surcharge_equivalence_amount' => $this->surchargeEquivalenceAmount,
            'category' => $this->category->value,
            'description' => $this->description,
            'client_or_supplier_nif' => $this->clientOrSupplierNif,
        ];
    }
}
