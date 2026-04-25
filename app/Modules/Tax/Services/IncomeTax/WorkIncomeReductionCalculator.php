<?php

namespace Modules\Tax\Services\IncomeTax;

use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Reducción por rendimientos del trabajo del art. 20 LIRPF.
 *
 * Aplica sobre el rendimiento neto previo (gastos sociales descontados).
 *
 * - Si rendimiento neto previo ≤ umbral_neto: reducción = mínima.
 * - Si entre umbral_neto y umbral_max: minima - 1,14 × (rendimiento - umbral_neto).
 * - Si > umbral_max: 0.
 *
 * Importes 2023-2026 según Ley 31/2022 (BOE-A-2022-22128):
 *   minima = 6.498 €, umbral_neto = 14.852 €, umbral_max = 19.747,50 €.
 *
 * NOTA: La reducción no puede dejar el rendimiento neto en negativo.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a20
 */
class WorkIncomeReductionCalculator
{
    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    /**
     * Calcula la reducción aplicable sobre un rendimiento neto previo (en euros).
     */
    public function calculate(FiscalYear $year, Money $previousNet): Money
    {
        if ($previousNet->isZero() || $previousNet->isNegative()) {
            return Money::zero();
        }

        $config = $this->repository->getParameter(
            $year,
            'irpf.reduccion_rendimientos_trabajo',
            RegionCode::state(),
        );

        if (! is_array($config)) {
            return Money::zero();
        }

        $minima = (float) ($config['minima'] ?? 0);
        $umbralMax = (float) ($config['umbral_max'] ?? 0);
        $umbralNeto = (float) ($config['umbral_neto'] ?? 0);

        if ($minima <= 0 || $umbralMax <= 0 || $umbralNeto <= 0) {
            return Money::zero();
        }

        $previousNetFloat = (float) $previousNet->amount;

        if ($previousNetFloat <= $umbralNeto) {
            $reduction = $minima;
        } elseif ($previousNetFloat <= $umbralMax) {
            $reduction = $minima - 1.14 * ($previousNetFloat - $umbralNeto);
        } else {
            $reduction = 0;
        }

        if ($reduction <= 0) {
            return Money::zero();
        }

        if ($reduction > $previousNetFloat) {
            $reduction = $previousNetFloat;
        }

        return Money::fromFloat($reduction);
    }
}
