<?php

namespace Modules\Tax\Services\Invoice;

use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Resuelve la retención IRPF aplicable a una factura emitida por un autónomo.
 *
 * Reglas (art. 95 RD 439/2007 Reglamento IRPF + art. 101 LIRPF):
 *  - Profesionales general: 15 %.
 *  - Profesionales en los 3 primeros años de actividad: 7 %.
 *    (Disposición adicional 31ª RIRPF / RD 145/2024 confirmando 7 % nuevos.)
 *  - Actividades agrícolas/ganaderas/forestales: 2 % (1 % engorde porcino y avicultura).
 *  - Actividades en módulos sometidas a retención (transporte mercancías,
 *    construcción, etc.): 1 %.
 *  - Rendimientos de capital mobiliario: 19 % (no aplica a facturas de servicios).
 *
 * El catálogo `activity_regime_mappings.irpf_retention_default` lleva la
 * retención por actividad concreta. Si no hay actividad o no aplica retención,
 * devuelve 0 % (típico cuando es venta de bienes a consumidor final).
 */
class IrpfRetentionResolver
{
    public const RATE_NEW_PROFESSIONAL = '7.00';

    public const RATE_PROFESSIONAL = '15.00';

    public const RATE_MODULES = '1.00';

    public const RATE_AGRICULTURE = '2.00';

    public const RATE_NONE = '0.00';

    public function resolve(
        RegimeCode $irpfRegime,
        FiscalYear $year,
        bool $newActivityFlag,
        ?string $activityCode = null,
    ): TaxRate {
        // Caso especial: nueva actividad profesional (3 primeros años) → 7 %.
        // Sólo aplica a profesionales (regímenes EDN/EDS); módulos no.
        if ($newActivityFlag && $this->isProfessionalRegime($irpfRegime)) {
            return TaxRate::fromPercentage(self::RATE_NEW_PROFESSIONAL);
        }

        // Resolver desde catálogo si hay activity_code.
        if ($activityCode !== null) {
            $rate = $this->lookupCatalogRate($activityCode);
            if ($rate !== null) {
                return TaxRate::fromPercentage($rate);
            }
        }

        // Default por régimen:
        //  - EDN/EDS: 15 % (asumimos profesional general si no hay catálogo).
        //  - EO (módulos): 0 % por defecto (sólo sectores específicos llevan 1 %,
        //    y esos vienen marcados en el mapping).
        return match ($irpfRegime->code) {
            'EDN', 'EDS' => TaxRate::fromPercentage(self::RATE_PROFESSIONAL),
            default => TaxRate::fromPercentage(self::RATE_NONE),
        };
    }

    private function isProfessionalRegime(RegimeCode $regime): bool
    {
        return in_array($regime->code, ['EDN', 'EDS'], true);
    }

    private function lookupCatalogRate(string $activityCode): ?string
    {
        $activity = EconomicActivity::query()
            ->where('code', $activityCode)
            ->orderByDesc('year')
            ->first();

        if ($activity === null) {
            return null;
        }

        $mapping = ActivityRegimeMapping::query()
            ->where('activity_id', $activity->id)
            ->first();

        if ($mapping === null || $mapping->irpf_retention_default === null) {
            return null;
        }

        // Forzamos string con 2 decimales para preservar precisión bcmath
        // (en la BD es decimal:2).
        return number_format((float) $mapping->irpf_retention_default, 2, '.', '');
    }
}
