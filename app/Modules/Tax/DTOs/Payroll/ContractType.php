<?php

namespace Modules\Tax\DTOs\Payroll;

/**
 * Tipo de contrato laboral por cuenta ajena para efectos de cotización a SS.
 *
 * Sólo afecta al tipo de cotización por desempleo (1.55 % indefinido vs 1.60 % temporal
 * para el trabajador y 5.50 % vs 6.70 % para la empresa). El resto de contingencias
 * son idénticas. Fuente: Orden anual de cotización (BOE).
 */
enum ContractType: string
{
    case Indefinido = 'indefinido';
    case Temporal = 'temporal';

    public function label(): string
    {
        return match ($this) {
            self::Indefinido => 'Indefinido',
            self::Temporal => 'Temporal',
        };
    }

    /**
     * Devuelve la contingencia de desempleo aplicable a este tipo de contrato.
     */
    public function unemploymentContingency(): string
    {
        return match ($this) {
            self::Indefinido => 'desempleo_indefinido',
            self::Temporal => 'desempleo_temporal',
        };
    }
}
