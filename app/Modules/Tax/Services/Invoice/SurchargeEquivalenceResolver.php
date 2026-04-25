<?php

namespace Modules\Tax\Services\Invoice;

use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resuelve el porcentaje de Recargo de Equivalencia aplicable cuando un
 * proveedor (régimen general de IVA) emite factura a un cliente minorista
 * acogido al Recargo de Equivalencia (art. 154-163 LIVA).
 *
 * Tipos vigentes (art. 161 LIVA):
 *   - Tipo general 21 %: recargo 5,2 %
 *   - Tipo reducido 10 %: recargo 1,4 %
 *   - Tipo superreducido 4 %: recargo 0,5 %
 *   - Tipo especial 5 %: recargo 0,62 % (introducido junto al 5 % temporal,
 *     RDL 4/2022 y prórrogas 2023-2024 — fuente: art. 161 LIVA y DA.
 *     Si el 5 % deja de estar vigente, también desaparece el 0,62 %.)
 *   - Tabacos: 1,75 % (no cubierto en MVP, requiere flag específico).
 *   - Cero / Exento: 0 %.
 *
 * Esta resolver es estática (sin BD) porque los porcentajes están en
 * la propia ley con vigencia indefinida. Si en el futuro hay cambios
 * legislativos, mover a vat_product_rates con rate_type='surcharge'.
 */
class SurchargeEquivalenceResolver
{
    public function resolve(VatRateType $rateType): TaxRate
    {
        return TaxRate::fromPercentage(match ($rateType) {
            VatRateType::GENERAL => '5.20',
            VatRateType::REDUCED => '1.40',
            VatRateType::SUPER_REDUCED => '0.50',
            VatRateType::SPECIAL => '0.62',
            VatRateType::ZERO, VatRateType::EXEMPT => '0.00',
        });
    }
}
