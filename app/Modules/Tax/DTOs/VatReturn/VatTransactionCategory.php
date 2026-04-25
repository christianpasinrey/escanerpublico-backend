<?php

namespace Modules\Tax\DTOs\VatReturn;

/**
 * Categoría informativa de la operación a efectos del modelo 303 / 390.
 *
 *  - DOMESTIC: operación interior España. Computa en casillas 01-09 (devengo)
 *    y 28-39 (deducible) del modelo 303.
 *  - INTRACOM: entregas/adquisiciones intracomunitarias. Casillas 10-11
 *    (devengo intracom) y 36-37 (deducible intracom) modelo 303. En MVP M7
 *    solo se anota como informativo (no se calcula IVA autorrepercutido).
 *  - IMPORTS: importaciones desde fuera UE. Casillas 32-33 modelo 303.
 *  - EXPORTS: exportaciones fuera UE. Casilla 60 (operaciones no sujetas /
 *    con derecho a deducción) modelo 303. No generan IVA repercutido.
 *
 * Fuente: Ley 37/1992 LIVA arts. 13-19 (intracomunitarias), arts. 21-25
 * (exportaciones / exenciones plenas). Orden HFP/3666/2024 modelo 303 2025+.
 */
enum VatTransactionCategory: string
{
    case DOMESTIC = 'domestic';
    case INTRACOM = 'intracom';
    case IMPORTS = 'imports';
    case EXPORTS = 'exports';

    public function label(): string
    {
        return match ($this) {
            self::DOMESTIC => 'Operación interior',
            self::INTRACOM => 'Operación intracomunitaria',
            self::IMPORTS => 'Importación',
            self::EXPORTS => 'Exportación',
        };
    }
}
