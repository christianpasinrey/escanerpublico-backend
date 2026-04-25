<?php

namespace Modules\Tax\Calculators\FractionalPayment;

use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentResult;

/**
 * Contrato común para las implementaciones de pago fraccionado por modelo.
 * El dispatcher FractionalPaymentCalculator selecciona la concreta según
 * `input->model`.
 */
interface FractionalPaymentRegimeCalculator
{
    public function calculate(FractionalPaymentInput $input): FractionalPaymentResult;
}
