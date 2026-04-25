<?php

namespace Modules\Tax\Calculators\Vat;

use Carbon\CarbonImmutable;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;

/**
 * Calculator IVA modelo 303 / 390 — Régimen Especial Criterio de Caja
 * (IVA_CAJA, art. 163 decies-sexiesdecies LIVA, introducido por Ley 14/2013).
 *
 * Devengo: regla especial — el IVA repercutido se devenga en el momento
 * del COBRO (no de la emisión). Tope: 31 de diciembre del año posterior
 * al de emisión (art. 163 terdecies LIVA: si transcurrido ese plazo no
 * se ha cobrado, devengo forzoso).
 *
 * Reglas para incluir una transacción en el período:
 *   - Si tx.paidDate ≠ null y paidDate cae dentro del período de
 *     liquidación → la transacción devenga en ese período.
 *   - Si tx.paidDate = null pero forcedAccrualDate (= 31-dic año posterior
 *     a tx.date) cae dentro del período → devengo forzoso.
 *   - En cualquier otro caso, la transacción no se incluye.
 *
 * Como el VatRegimeValidator exige paidDate en todas las transacciones
 * en MVP M7 (para evitar ambigüedades), la regla del devengo forzoso
 * solo se aplicaría si en el futuro se relaja la validación.
 *
 * Misma lógica de agrupación, casillas y resultado que RegimenGeneralVat
 * — solo cambia la fecha que decide la inclusión en el período.
 *
 * Fuente:
 *  - Ley 37/1992 LIVA, arts. 163 decies-sexiesdecies (Régimen Especial
 *    Criterio de Caja).
 *  - RD 1624/1992 RIVA, art. 61 sexies (formalidades del régimen).
 */
class CriterioCajaVat extends RegimenGeneralVat
{
    public function calculate(VatReturnInput $input): VatReturnResult
    {
        return $this->doCalculate($input, useCriterioCaja: true);
    }

    /**
     * Sobrescribe la fecha de devengo: usa paidDate si existe, o el
     * forcedAccrualDate (31-dic año posterior).
     */
    protected function criterioCajaAccrualDate(VatTransactionInput $tx): CarbonImmutable
    {
        if ($tx->paidDate !== null) {
            return $tx->paidDate;
        }

        return $tx->forcedAccrualDate();
    }
}
