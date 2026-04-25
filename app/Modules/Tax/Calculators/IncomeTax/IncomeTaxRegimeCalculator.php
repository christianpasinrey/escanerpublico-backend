<?php

namespace Modules\Tax\Calculators\IncomeTax;

use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxResult;

/**
 * Contrato común para todas las implementaciones por régimen del IRPF anual
 * (modelo 100). El dispatcher IncomeTaxCalculator selecciona la concreta según
 * `input->regime->code`.
 */
interface IncomeTaxRegimeCalculator
{
    public function calculate(IncomeTaxInput $input): IncomeTaxResult;
}
