<?php

namespace Modules\Tax\Calculators\Invoice;

use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceResult;

/**
 * Contrato común de los calculators específicos por régimen de IVA.
 * El dispatcher InvoiceCalculator los selecciona según issuerVatRegime.
 */
interface InvoiceRegimeCalculator
{
    public function calculate(InvoiceInput $input): InvoiceResult;
}
