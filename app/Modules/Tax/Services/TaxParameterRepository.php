<?php

namespace Modules\Tax\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Tax\Models\AutonomoBracket;
use Modules\Tax\Models\SocialSecurityRate;
use Modules\Tax\Models\TaxBracket;
use Modules\Tax\Models\TaxParameter;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;

/**
 * Acceso cacheado a parámetros, escalas, tipos SS y tramos de autónomos.
 * Cache Redis sin TTL, invalidado manualmente vía bust() tras reseed.
 */
class TaxParameterRepository
{
    public const CACHE_PREFIX = 'tax:params:v1';

    /**
     * Recupera un parámetro por clave para un año y región.
     * Si no hay regional específico, cae a estado.
     */
    public function getParameter(FiscalYear $year, string $key, ?RegionCode $region = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.":param:{$year}:{$key}:".($region?->code ?? RegionCode::STATE);

        return Cache::rememberForever($cacheKey, function () use ($year, $key, $region) {
            if ($region !== null && ! $region->isState()) {
                $regional = TaxParameter::query()
                    ->where('year', $year->year)
                    ->where('region_code', $region->code)
                    ->where('key', $key)
                    ->first();

                if ($regional !== null) {
                    return $regional->value;
                }
            }

            $stateParam = TaxParameter::query()
                ->where('year', $year->year)
                ->whereNull('region_code')
                ->where('key', $key)
                ->first();

            return $stateParam?->value;
        });
    }

    /**
     * Devuelve la escala de tramos ordenada por from_amount asc.
     *
     * @return Collection<int, TaxBracket>
     */
    public function getBrackets(
        FiscalYear $year,
        string $type,
        string $scope = 'state',
        ?RegionCode $region = null,
    ): Collection {
        $cacheKey = self::CACHE_PREFIX.":brackets:{$year}:{$scope}:{$type}:".($region?->code ?? '');

        return Cache::rememberForever($cacheKey, function () use ($year, $type, $scope, $region) {
            $query = TaxBracket::query()
                ->where('year', $year->year)
                ->where('scope', $scope)
                ->where('type', $type)
                ->orderBy('from_amount');

            if ($region !== null && ! $region->isState()) {
                $query->where('region_code', $region->code);
            } else {
                $query->whereNull('region_code');
            }

            return $query->get();
        });
    }

    /**
     * Tipos de cotización SS para un año, régimen y contingencia.
     */
    public function getSocialSecurityRate(
        FiscalYear $year,
        string $regime,
        string $contingency,
    ): ?SocialSecurityRate {
        $cacheKey = self::CACHE_PREFIX.":ss:{$year}:{$regime}:{$contingency}";

        return Cache::rememberForever($cacheKey, function () use ($year, $regime, $contingency) {
            return SocialSecurityRate::query()
                ->where('year', $year->year)
                ->where('regime', $regime)
                ->where('contingency', $contingency)
                ->first();
        });
    }

    /**
     * Todos los tramos de autónomos para un año, ordenados.
     *
     * @return Collection<int, AutonomoBracket>
     */
    public function getAutonomoBrackets(FiscalYear $year): Collection
    {
        $cacheKey = self::CACHE_PREFIX.":autonomo:{$year}";

        return Cache::rememberForever($cacheKey, function () use ($year) {
            return AutonomoBracket::query()
                ->where('year', $year->year)
                ->orderBy('bracket_number')
                ->get();
        });
    }

    /**
     * Devuelve el tramo de autónomos correspondiente al rendimiento mensual dado.
     */
    public function findAutonomoBracketByYield(FiscalYear $year, string $monthlyYield): AutonomoBracket
    {
        foreach ($this->getAutonomoBrackets($year) as $bracket) {
            $aboveMin = bccomp($monthlyYield, (string) $bracket->from_yield, 2) >= 0;
            $belowMax = $bracket->to_yield === null
                || bccomp($monthlyYield, (string) $bracket->to_yield, 2) <= 0;

            if ($aboveMin && $belowMax) {
                return $bracket;
            }
        }

        throw new RuntimeException("No se encontró tramo de autónomos para rendimiento {$monthlyYield} en {$year}");
    }

    /**
     * Invalida toda la caché del repositorio. Llamar tras reseed.
     */
    public function bust(): void
    {
        // Cache::tags() no está disponible en todos los stores. Para Redis
        // invalidamos por patrón scan en producción; en MVP basta flush por prefijo
        // mediante helper que recorre las claves conocidas. Reseed es manual y poco
        // frecuente, así que un flush global del cache es aceptable también.
        Cache::flush();
    }
}
