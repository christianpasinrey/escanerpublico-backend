<?php

namespace Modules\Tax\Services\Vat;

use InvalidArgumentException;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;

/**
 * Valida que el conjunto de transacciones de una autoliquidación de IVA
 * sea coherente con el régimen elegido.
 *
 * Reglas:
 *
 *  1. IVA_CAJA (Régimen Especial Criterio de Caja, art. 163 decies LIVA):
 *     todas las transacciones outgoing/incoming deben llevar paidDate
 *     o, en su defecto, el calculator usará la fecha de devengo forzoso
 *     31-dic año posterior. Si una transacción NO tiene paidDate y la
 *     fecha de devengo + 1 año aún no ha llegado, se considera no
 *     devengada en el período. Para evitar ambigüedades en MVP, exigimos
 *     paidDate en todas las transacciones.
 *
 *  2. IVA_SIMPLE (Régimen Simplificado, art. 122 LIVA):
 *     a) `simplifiedModulesData` obligatorio (validado en VatReturnInput).
 *     b) Todas las transacciones incoming (compras) son aceptadas para
 *        deducción del IVA soportado (sólo % autoaplicado en MVP).
 *     c) Las transacciones outgoing NO se procesan en este régimen para
 *        IVA devengado: la cuota anual la fijan los módulos. Si llegan
 *        outgoing, se anotarán como informativos sin afectar al cálculo.
 *
 *  3. IVA_GEN (Régimen General):
 *     Sin restricciones especiales más allá de la validación interna del
 *     DTO. paidDate es opcional (se ignora — el devengo es por emisión).
 */
class VatRegimeValidator
{
    public function validate(VatReturnInput $input): void
    {
        match ($input->regime->code) {
            'IVA_CAJA' => $this->validateCriterioCaja($input),
            'IVA_SIMPLE' => $this->validateSimplificado($input),
            'IVA_GEN' => null, // sin reglas extra
            default => throw new InvalidArgumentException(
                "Régimen IVA '{$input->regime->code}' fuera del alcance del MVP M7."
            ),
        };
    }

    private function validateCriterioCaja(VatReturnInput $input): void
    {
        foreach ($input->transactions as $idx => $tx) {
            /** @var VatTransactionInput $tx */
            if ($tx->paidDate === null) {
                throw new InvalidArgumentException(
                    "Transacción #{$idx} sin paidDate: el régimen de Criterio de Caja ".
                    '(art. 163 decies LIVA) requiere fecha real de cobro/pago en cada operación.'
                );
            }
        }
    }

    private function validateSimplificado(VatReturnInput $input): void
    {
        // simplifiedModulesData ya validado obligatorio en VatReturnInput.
        // Aquí validamos que la lista `modules` esté presente y no vacía:
        // sin módulos no hay cuota anual devengada que calcular.
        $modulesData = $input->simplifiedModulesData ?? [];
        $modules = $modulesData['modules'] ?? [];
        if (! is_array($modules) || $modules === []) {
            throw new InvalidArgumentException(
                'El régimen simplificado requiere al menos un módulo en simplifiedModulesData.modules.'
            );
        }
    }
}
