<?php

namespace Modules\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Situación personal y familiar del contribuyente para el cálculo del IRPF anual
 * (modelo 100). Equivale al bloque "datos personales" del programa Renta Web.
 *
 * Inmutable. No persiste en BD.
 *
 * Campos:
 *  - married: convivencia matrimonial (afecta declaración conjunta — fuera MVP).
 *  - spouseHasIncome: cónyuge con rentas anuales > 1.500 € (informativo MVP).
 *  - descendants: número total de hijos a cargo (incluye los menores de 3).
 *  - descendantsUnder3: subset de descendants menores de 3 años (incremento art. 58 LIRPF).
 *  - ascendantsOver65Living: ascendientes mayores de 65 años a cargo y conviviendo.
 *  - ascendantsDisabledLiving: ascendientes con discapacidad ≥ 33 % conviviendo a cargo.
 *  - disabilityPercent: porcentaje de discapacidad reconocido (>= 33). null si no aplica.
 *  - ageAtYearEnd: edad del contribuyente al cierre del ejercicio (>= 0). Si null,
 *    no aplica el incremento mínimo personal por edad (art. 57 LIRPF).
 *
 * Fuente: Ley 35/2006 art. 56-61 (BOE-A-2006-20764).
 */
final readonly class TaxpayerSituation implements JsonSerializable
{
    public function __construct(
        public bool $married = false,
        public bool $spouseHasIncome = true,
        public int $descendants = 0,
        public int $descendantsUnder3 = 0,
        public int $ascendantsOver65Living = 0,
        public int $ascendantsDisabledLiving = 0,
        public ?int $disabilityPercent = null,
        public ?int $ageAtYearEnd = null,
    ) {
        if ($descendants < 0 || $descendantsUnder3 < 0) {
            throw new InvalidArgumentException('descendants y descendantsUnder3 no pueden ser negativos');
        }

        if ($descendantsUnder3 > $descendants) {
            throw new InvalidArgumentException(
                'descendantsUnder3 no puede ser mayor que descendants',
            );
        }

        if ($ascendantsOver65Living < 0 || $ascendantsDisabledLiving < 0) {
            throw new InvalidArgumentException('ascendants no pueden ser negativos');
        }

        if ($disabilityPercent !== null && ($disabilityPercent < 0 || $disabilityPercent > 100)) {
            throw new InvalidArgumentException(
                "disabilityPercent debe estar entre 0 y 100: {$disabilityPercent}",
            );
        }

        if ($ageAtYearEnd !== null && ($ageAtYearEnd < 0 || $ageAtYearEnd > 120)) {
            throw new InvalidArgumentException("ageAtYearEnd fuera de rango: {$ageAtYearEnd}");
        }
    }

    public static function single(): self
    {
        return new self;
    }

    public function jsonSerialize(): array
    {
        return [
            'married' => $this->married,
            'spouse_has_income' => $this->spouseHasIncome,
            'descendants' => $this->descendants,
            'descendants_under_3' => $this->descendantsUnder3,
            'ascendants_over_65_living' => $this->ascendantsOver65Living,
            'ascendants_disabled_living' => $this->ascendantsDisabledLiving,
            'disability_percent' => $this->disabilityPercent,
            'age_at_year_end' => $this->ageAtYearEnd,
        ];
    }
}
