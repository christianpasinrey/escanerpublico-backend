<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\ValueObjects\Money;
use RuntimeException;

/**
 * Simulador del IRPF anual para asalariado puro (régimen ASALARIADO_GEN).
 *
 * Reutiliza la lógica de `AbstractIncomeTaxRegime`:
 *  - Rendimiento neto del trabajo = bruto − SS − reducción art. 20 LIRPF.
 *  - No hay rendimientos de actividades económicas.
 *
 * Útil para que un trabajador por cuenta ajena pueda simular su Renta sin
 * tener que pasar por la calculadora mensual de nómina (M4).
 *
 * Casos de uso:
 *   - Trabajador con un único pagador que quiere ver el resultado anual.
 *   - Trabajador que sabe el bruto anual y las retenciones acumuladas y quiere
 *     saber si le toca pagar o devolver.
 */
class RendimientosTrabajoSimulator extends AbstractIncomeTaxRegime
{
    /**
     * @param  list<BreakdownLine>  $lines
     */
    protected function activityNet(IncomeTaxInput $input, array &$lines): Money
    {
        if ($input->workIncome === null) {
            throw new RuntimeException(
                'El régimen ASALARIADO_GEN requiere workIncome.',
            );
        }

        // Asalariado puro = no tiene actividades económicas; rendimiento = 0.
        return Money::zero();
    }
}
