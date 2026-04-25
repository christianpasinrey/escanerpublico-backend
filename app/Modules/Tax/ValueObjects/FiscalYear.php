<?php

namespace Modules\Tax\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Ejercicio fiscal en España (coincide con año natural).
 * Provee helpers para calcular trimestres y plazos asociados.
 */
final readonly class FiscalYear implements JsonSerializable, Stringable
{
    public const MIN_SUPPORTED = 2023;

    public function __construct(public int $year)
    {
        if ($year < self::MIN_SUPPORTED || $year > 2100) {
            throw new InvalidArgumentException("FiscalYear fuera de rango soportado: {$year}");
        }
    }

    public static function current(): self
    {
        return new self((int) date('Y'));
    }

    public static function fromInt(int $year): self
    {
        return new self($year);
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, 1, 1, 0, 0, 0);
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, 12, 31, 23, 59, 59);
    }

    public function quarterStart(int $quarter): CarbonImmutable
    {
        $this->assertQuarter($quarter);
        $month = (($quarter - 1) * 3) + 1;

        return CarbonImmutable::create($this->year, $month, 1, 0, 0, 0);
    }

    public function quarterEnd(int $quarter): CarbonImmutable
    {
        $this->assertQuarter($quarter);

        return $this->quarterStart($quarter)->addMonths(3)->subDay()->endOfDay();
    }

    public function previous(): self
    {
        return new self($this->year - 1);
    }

    public function next(): self
    {
        return new self($this->year + 1);
    }

    public function jsonSerialize(): int
    {
        return $this->year;
    }

    public function __toString(): string
    {
        return (string) $this->year;
    }

    private function assertQuarter(int $quarter): void
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Trimestre fuera de rango: {$quarter}");
        }
    }
}
