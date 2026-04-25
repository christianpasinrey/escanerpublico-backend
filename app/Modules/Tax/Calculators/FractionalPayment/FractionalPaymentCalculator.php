<?php

namespace Modules\Tax\Calculators\FractionalPayment;

use InvalidArgumentException;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentResult;

/**
 * Dispatcher principal de la calculadora de pagos fraccionados IRPF
 * (modelos 130 y 131).
 *
 * Selecciona la implementación según `input->model`:
 *  - MODELO_130 (Estimación Directa Normal/Simplificada) → Modelo130Payment
 *  - MODELO_131 (Estimación Objetiva)                    → Modelo131Payment
 *
 * Cualquier otro modelo (202 Sociedades, etc.) se rechaza con
 * InvalidArgumentException porque está fuera de alcance MVP.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a110 (RIRPF art. 110)
 */
class FractionalPaymentCalculator
{
    public function __construct(
        private readonly Modelo130Payment $modelo130,
        private readonly Modelo131Payment $modelo131,
    ) {}

    public function calculate(FractionalPaymentInput $input): FractionalPaymentResult
    {
        return $this->resolveImplementation($input)->calculate($input);
    }

    private function resolveImplementation(FractionalPaymentInput $input): FractionalPaymentRegimeCalculator
    {
        return match ($input->model) {
            FractionalPaymentModel::MODELO_130 => $this->modelo130,
            FractionalPaymentModel::MODELO_131 => $this->modelo131,
        };
    }

    /**
     * Helper expuesto para tests unitarios y validaciones HTTP — verifica
     * que el régimen y el modelo son compatibles antes de instanciar
     * FractionalPaymentInput (que ya valida internamente).
     */
    public function assertCompatible(FractionalPaymentModel $model, string $regimeCode): void
    {
        if (! in_array($regimeCode, $model->compatibleRegimes(), true)) {
            $compatible = implode(', ', $model->compatibleRegimes());
            throw new InvalidArgumentException(
                "El régimen '{$regimeCode}' no es compatible con el modelo {$model->value}. ".
                "Regímenes compatibles: {$compatible}.",
            );
        }
    }
}
