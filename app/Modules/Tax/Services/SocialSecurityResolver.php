<?php

namespace Modules\Tax\Services;

use Modules\Tax\Models\SocialSecurityRate;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use RuntimeException;

/**
 * Resuelve bases mín/máx y tipos de cotización a la SS por año, régimen y
 * contingencia consultando `social_security_rates` vía TaxParameterRepository.
 *
 * Fuente: Orden anual de cotización (BOE) — RD-ley 13/2022 + RD-ley 8/2023
 * para el MEI, RD-ley 28/2018 para ATEP autónomos, etc.
 */
class SocialSecurityResolver
{
    public const REGIMEN_GENERAL = 'RG';

    public const CONTINGENCY_COMMON = 'contingencias_comunes';

    public const CONTINGENCY_FP = 'fp';

    public const CONTINGENCY_FOGASA = 'fogasa';

    public const CONTINGENCY_MEI = 'mei';

    public const CONTINGENCY_ATEP = 'atep';

    public const CONTINGENCY_DESEMPLEO_INDEFINIDO = 'desempleo_indefinido';

    public const CONTINGENCY_DESEMPLEO_TEMPORAL = 'desempleo_temporal';

    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    /**
     * Devuelve la base mensual de cotización capada entre la base mínima y la máxima
     * de contingencias comunes para el régimen dado.
     */
    public function cappedMonthlyBase(FiscalYear $year, string $regime, Money $monthlyGross): Money
    {
        $rate = $this->mustFind($year, $regime, self::CONTINGENCY_COMMON);

        $base = $monthlyGross;

        if ($rate->base_min !== null) {
            $min = Money::fromFloat((float) $rate->base_min);
            if ($base->compare($min) < 0) {
                $base = $min;
            }
        }

        if ($rate->base_max !== null) {
            $max = Money::fromFloat((float) $rate->base_max);
            if ($base->compare($max) > 0) {
                $base = $max;
            }
        }

        return $base;
    }

    /**
     * Tipo del trabajador para una contingencia (porcentaje, ej. 4.70 para CC).
     */
    public function employeeRate(FiscalYear $year, string $regime, string $contingency): TaxRate
    {
        $rate = $this->mustFind($year, $regime, $contingency);

        return TaxRate::fromPercentage((float) $rate->rate_employee);
    }

    /**
     * Tipo de la empresa para una contingencia (porcentaje).
     */
    public function employerRate(FiscalYear $year, string $regime, string $contingency): TaxRate
    {
        $rate = $this->mustFind($year, $regime, $contingency);

        return TaxRate::fromPercentage((float) $rate->rate_employer);
    }

    /**
     * Aplica el tipo del trabajador sobre la base devolviendo la cuota.
     */
    public function employeeQuota(FiscalYear $year, string $regime, string $contingency, Money $base): Money
    {
        return $base->applyRate($this->employeeRate($year, $regime, $contingency));
    }

    /**
     * Aplica el tipo de la empresa sobre la base devolviendo la cuota.
     */
    public function employerQuota(FiscalYear $year, string $regime, string $contingency, Money $base): Money
    {
        return $base->applyRate($this->employerRate($year, $regime, $contingency));
    }

    private function mustFind(FiscalYear $year, string $regime, string $contingency): SocialSecurityRate
    {
        $rate = $this->repository->getSocialSecurityRate($year, $regime, $contingency);
        if ($rate === null) {
            throw new RuntimeException(
                "No hay tipo de cotización SS sembrado para year={$year->year}, regime={$regime}, contingency={$contingency}",
            );
        }

        return $rate;
    }
}
