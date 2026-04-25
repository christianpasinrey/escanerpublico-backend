<?php

namespace Modules\Tax\DTOs\Payroll;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Input para la calculadora de nómina (régimen general — asalariado por cuenta ajena).
 *
 * Inmutable. No persiste en BD.  Toda la lógica de cálculo se ejecuta en memoria
 * para evitar fugas de PII (sueldo, situación familiar) en logs o caché.
 *
 * paymentsCount: 12 (sin extras prorrateadas) o 14 (con extras prorrateadas).
 * disabilityPercent: porcentaje de discapacidad reconocido (>=33). null si no aplica.
 * descendants: número total de hijos a cargo (incluye los menores de 3).
 * descendantsUnder3: subset de descendants que son < 3 años (incremento art. 58 LIRPF).
 * ascendantsOver65Living: número de ascendientes > 65 que conviven a cargo.
 * ascendantsDisabledLiving: ascendientes con discapacidad ≥33 % conviviendo a cargo.
 * married: convivencia matrimonial.
 * spouseHasIncome: cónyuge con rentas anuales > 1.500 € (afecta tipo de retención).
 * contractType: indefinido/temporal — afecta al tipo de cotización por desempleo.
 */
final readonly class PayrollInput
{
    public function __construct(
        public Money $grossAnnual,
        public int $paymentsCount,
        public RegionCode $region,
        public FiscalYear $year,
        public ContractType $contractType,
        public ?CarbonImmutable $birthDate = null,
        public ?int $disabilityPercent = null,
        public int $descendants = 0,
        public int $descendantsUnder3 = 0,
        public int $ascendantsOver65Living = 0,
        public int $ascendantsDisabledLiving = 0,
        public bool $married = false,
        public bool $spouseHasIncome = true,
    ) {
        if ($paymentsCount !== 12 && $paymentsCount !== 14) {
            throw new InvalidArgumentException(
                "paymentsCount debe ser 12 o 14, recibido: {$paymentsCount}",
            );
        }

        if ($disabilityPercent !== null && ($disabilityPercent < 0 || $disabilityPercent > 100)) {
            throw new InvalidArgumentException(
                "disabilityPercent debe estar entre 0 y 100: {$disabilityPercent}",
            );
        }

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

        if ($grossAnnual->isNegative()) {
            throw new InvalidArgumentException('grossAnnual no puede ser negativo');
        }
    }

    /**
     * Devuelve la edad del contribuyente al final del ejercicio fiscal,
     * útil para mínimo personal por edad (>65, >75 — art. 57 LIRPF).
     */
    public function ageAtFiscalYearEnd(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }

        return $this->birthDate->diffInYears($this->year->end());
    }

    /**
     * Bruto mensual prorrateado (= bruto anual / paymentsCount).
     */
    public function monthlyGross(): Money
    {
        return $this->grossAnnual->divide($this->paymentsCount);
    }
}
