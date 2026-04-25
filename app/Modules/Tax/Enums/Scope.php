<?php

namespace Modules\Tax\Enums;

/**
 * Ámbito territorial / nivel administrativo de un tributo.
 *
 * - state: tributos cuya titularidad y potestad normativa corresponden al Estado
 *   (IRPF, IVA, IS, IIEE, tasas estatales). Los tributos cedidos parcialmente
 *   a las CCAA (ITP/AJD/ISD/IP) tienen su versión estatal con `state` y sus
 *   versiones específicas autonómicas con `regional`.
 * - regional: tributos propios de las CCAA o cedidos con potestad normativa
 *   (tributos sobre el juego, tasas autonómicas, recargos sobre IRPF…).
 * - local: tributos propios de las entidades locales (IBI, IVTM, IIVTNU,
 *   ICIO, tasas municipales, contribuciones especiales).
 *
 * Referencia: Constitución Española art. 133, LGT 58/2003 art. 4,
 * Ley Orgánica 8/1980 LOFCA, Real Decreto Legislativo 2/2004 TRLRHL.
 */
enum Scope: string
{
    case State = 'state';
    case Regional = 'regional';
    case Local = 'local';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::State->value => 'Estatal',
            self::Regional->value => 'Autonómico',
            self::Local->value => 'Local',
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
