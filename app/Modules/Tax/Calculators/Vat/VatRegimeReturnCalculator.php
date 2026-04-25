<?php

namespace Modules\Tax\Calculators\Vat;

use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;

/**
 * Contrato común de los calculators específicos por régimen IVA del módulo
 * de autoliquidaciones (modelo 303 / 390). El dispatcher VatReturnCalculator
 * los selecciona según `regime`.
 */
interface VatRegimeReturnCalculator
{
    public function calculate(VatReturnInput $input): VatReturnResult;
}
