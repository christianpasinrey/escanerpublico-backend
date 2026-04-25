<?php

namespace Modules\Tax\DTOs\VatReturn;

/**
 * Sentido de una operación a efectos de IVA en una autoliquidación
 * (modelo 303 / 390).
 *
 *  - OUTGOING: el sujeto pasivo emite la factura (IVA repercutido / devengado).
 *    Casillas devengo modelo 303 (sección "IVA devengado", filas 01..09).
 *  - INCOMING: el sujeto pasivo recibe la factura (IVA soportado deducible).
 *    Casillas IVA deducible modelo 303 (sección "IVA deducible", filas 28..39).
 *
 * Fuente: Orden HFP/3666/2024, de 11 de diciembre, modelo 303 vigente
 *  (BOE 19-12-2024) — secciones devengo/deducible.
 */
enum VatTransactionDirection: string
{
    case OUTGOING = 'outgoing';
    case INCOMING = 'incoming';

    public function label(): string
    {
        return match ($this) {
            self::OUTGOING => 'IVA devengado (factura emitida)',
            self::INCOMING => 'IVA soportado (factura recibida)',
        };
    }
}
