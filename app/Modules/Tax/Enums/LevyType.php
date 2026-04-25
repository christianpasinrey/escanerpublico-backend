<?php

namespace Modules\Tax\Enums;

/**
 * Tipo de gravamen tributario.
 *
 * - impuesto: tributo cuyo hecho imponible no se relaciona con un servicio o
 *   actividad administrativa concreta (IRPF, IVA, IS, ITP, IIEE, IBI…).
 * - tasa: contraprestación por la utilización privativa del dominio público,
 *   prestación de servicios o realización de actividades en régimen de derecho
 *   público (TASA_DNI, TASA_JUDICIAL, TASA_PORTUARIA, tasas universitarias…).
 * - contribucion: contribución especial por obra pública o ampliación de servicios
 *   (alumbrado público, alcantarillado…).
 *
 * Referencia: Ley 58/2003 General Tributaria, art. 2.2.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2003-23186
 */
enum LevyType: string
{
    case Impuesto = 'impuesto';
    case Tasa = 'tasa';
    case Contribucion = 'contribucion';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Impuesto->value => 'Impuesto',
            self::Tasa->value => 'Tasa',
            self::Contribucion->value => 'Contribución especial',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
