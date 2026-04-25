<?php

namespace Modules\Tax\DTOs;

enum BreakdownCategory: string
{
    case BASE = 'base';
    case CONTRIBUTION = 'contribution';
    case TAX = 'tax';
    case REDUCTION = 'reduction';
    case DEDUCTION = 'deduction';
    case ADDITION = 'addition';
    case NET = 'net';
    case INFO = 'info';

    public function label(): string
    {
        return match ($this) {
            self::BASE => 'Base',
            self::CONTRIBUTION => 'Cotización',
            self::TAX => 'Impuesto',
            self::REDUCTION => 'Reducción',
            self::DEDUCTION => 'Deducción',
            self::ADDITION => 'Adición',
            self::NET => 'Resultado neto',
            self::INFO => 'Informativo',
        };
    }
}
