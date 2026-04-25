<?php

namespace Modules\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Input para la calculadora de IRPF anual modelo 100.
 *
 * Inmutable. Stateless — el calculator no persiste nada en BD.
 *
 * Validaciones de dominio:
 *  - El régimen debe ser de scope 'irpf' (EDN, EDS, EO o ASALARIADO_GEN).
 *  - Si régimen es ASALARIADO_GEN, workIncome es obligatorio.
 *  - Si régimen es EDN, EDS o EO, economicActivity es obligatorio.
 *  - Año soportado: 2023 → 2026 (FiscalYear).
 *
 * Disclaimer legal: cálculo informativo, no asesoramiento fiscal. La declaración
 * real del modelo 100 incluye rendimientos del capital, ganancias patrimoniales,
 * pluriempleo, etc., que están fuera del alcance del MVP.
 */
final readonly class IncomeTaxInput implements JsonSerializable
{
    public function __construct(
        public RegimeCode $regime,
        public FiscalYear $year,
        public RegionCode $region,
        public TaxpayerSituation $taxpayerSituation,
        public ?WorkIncomeInput $workIncome = null,
        public ?EconomicActivityInput $economicActivity = null,
    ) {
        if (! $regime->isIrpf()) {
            throw new InvalidArgumentException(
                "El régimen del IRPF debe ser de scope 'irpf', recibido '{$regime->scope()}'.",
            );
        }

        if ($regime->code === 'ASALARIADO_GEN' && $workIncome === null) {
            throw new InvalidArgumentException(
                "El régimen ASALARIADO_GEN requiere workIncome.",
            );
        }

        if (in_array($regime->code, ['EDN', 'EDS', 'EO'], true) && $economicActivity === null) {
            throw new InvalidArgumentException(
                "El régimen {$regime->code} requiere economicActivity.",
            );
        }

        // Si declara EO, EO no admite gastos deducibles reales.
        if ($regime->code === 'EO' && $economicActivity !== null
            && ! $economicActivity->deductibleExpenses->isZero()) {
            throw new InvalidArgumentException(
                'En Estimación Objetiva (EO) no aplican gastos deducibles reales: '
                .'el rendimiento se determina por módulos (signos/índices). '
                .'deductibleExpenses debe ser 0.',
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'regime' => $this->regime,
            'year' => $this->year->year,
            'region' => $this->region,
            'taxpayer_situation' => $this->taxpayerSituation,
            'work_income' => $this->workIncome,
            'economic_activity' => $this->economicActivity,
        ];
    }
}
