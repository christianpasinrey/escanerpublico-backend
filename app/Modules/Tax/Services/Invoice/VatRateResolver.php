<?php

namespace Modules\Tax\Services\Invoice;

use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Models\VatProductRate;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resuelve el tipo de IVA aplicable a una línea de factura.
 *
 * Estrategia:
 *  1. Si hay activity_code o keyword + año, busca en `vat_product_rates` la
 *     fila más específica que coincida con el rate_type pedido.
 *  2. Si no existe, cae al tipo nominal por defecto del enum (21/10/4/5/0).
 *
 * Nota: Ley 37/1992 LIVA — arts. 90 (general 21 %), 91.uno (reducido 10 %),
 * 91.dos (superreducido 4 %), 20 (exenciones).
 *
 * Cache: la consulta a vat_product_rates se hace solo una vez por
 * (year, vatRateType, activityCode|keyword). No se cachea entre requests
 * porque la cardinalidad efectiva en una factura es muy pequeña.
 */
class VatRateResolver
{
    /**
     * Resuelve el tipo aplicable. Devuelve siempre un TaxRate (porcentaje),
     * incluso para EXEMPT y ZERO (0.00 %).
     */
    public function resolve(
        VatRateType $rateType,
        FiscalYear $year,
        ?string $activityCode = null,
        ?string $keyword = null,
    ): TaxRate {
        if ($rateType === VatRateType::EXEMPT) {
            return TaxRate::zero();
        }

        $catalog = $this->lookupCatalog($rateType, $year, $activityCode, $keyword);
        if ($catalog !== null) {
            return TaxRate::fromPercentage($catalog);
        }

        return TaxRate::fromPercentage($rateType->defaultRatePercentage());
    }

    private function lookupCatalog(
        VatRateType $rateType,
        FiscalYear $year,
        ?string $activityCode,
        ?string $keyword,
    ): ?string {
        $query = VatProductRate::query()
            ->where('year', $year->year)
            ->where('rate_type', $rateType->value);

        if ($activityCode !== null) {
            $row = (clone $query)->where('activity_code', $activityCode)->first();
            if ($row !== null) {
                return (string) $row->rate;
            }
        }

        if ($keyword !== null) {
            $row = (clone $query)->where('keyword', $keyword)->first();
            if ($row !== null) {
                return (string) $row->rate;
            }
        }

        return null;
    }
}
