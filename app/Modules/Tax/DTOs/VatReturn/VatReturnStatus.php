<?php

namespace Modules\Tax\DTOs\VatReturn;

/**
 * Resultado neto de la autoliquidación de IVA (modelo 303 / 390).
 *
 *  - A_INGRESAR: cuota líquida positiva — el contribuyente debe ingresar
 *    el importe a la AEAT (casilla 71 = positivo modelo 303).
 *  - A_COMPENSAR: cuota líquida negativa — se traslada a períodos futuros
 *    (casilla 72 = positivo modelo 303). Aplicable en 1T, 2T, 3T y casos
 *    en 4T donde el contribuyente prefiera compensar.
 *  - A_DEVOLVER: cuota líquida negativa que se solicita devolver
 *    (casilla 73 modelo 303). Solo en último período (4T o anual 390),
 *    o en cualquier período si el contribuyente está en REDEME (Registro
 *    de Devolución Mensual, art. 30 RIVA).
 *
 * Fuente: Orden HFP/3666/2024 — instrucciones modelo 303,
 * apartado "Resultado de la liquidación".
 */
enum VatReturnStatus: string
{
    case A_INGRESAR = 'a_ingresar';
    case A_COMPENSAR = 'a_compensar';
    case A_DEVOLVER = 'a_devolver';

    public function label(): string
    {
        return match ($this) {
            self::A_INGRESAR => 'A ingresar',
            self::A_COMPENSAR => 'A compensar',
            self::A_DEVOLVER => 'A devolver',
        };
    }
}
