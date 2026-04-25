<?php

namespace Modules\Tax\Calculators\Payroll;

use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\DTOs\Payroll\PayrollResult;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;

/**
 * Dispatcher principal de la calculadora de nómina.
 *
 * En MVP solo soporta Régimen General SS + IRPF estatal+autonómica para
 * las 4 CCAA top (Madrid, Cataluña, Andalucía, C.Valenciana).
 *
 * Régimen foral (PV/Navarra) y Beckham están explícitamente fuera de alcance
 * y se rechazan con excepción.
 */
class PayrollCalculator
{
    public function __construct(
        private readonly RegimenGeneralPayroll $regimenGeneral,
    ) {}

    public function calculate(PayrollInput $input): PayrollResult
    {
        if ($input->region->isForal()) {
            throw new RuntimeException(
                'Régimen foral (País Vasco / Navarra) está fuera del alcance del MVP. '
                .'Estas comunidades disponen de su propio sistema fiscal con escalas y deducciones distintas.',
            );
        }

        $supportedRegions = ['MD', 'CT', 'AN', 'VC', RegionCode::STATE];
        if (! in_array($input->region->code, $supportedRegions, true)) {
            throw new RuntimeException(
                "La región {$input->region->name()} no está cubierta en MVP. "
                .'CCAA soportadas: Madrid, Cataluña, Andalucía y Comunidad Valenciana.',
            );
        }

        return $this->regimenGeneral->calculate($input);
    }
}
