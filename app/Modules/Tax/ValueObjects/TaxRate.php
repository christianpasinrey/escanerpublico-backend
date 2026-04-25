<?php

namespace Modules\Tax\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Tipo impositivo expresado como porcentaje. Ej: 21.00 = 21 %.
 * Inmutable, precisión decimal vía bcmath (escala 4 internamente).
 */
final readonly class TaxRate implements JsonSerializable, Stringable
{
    public const SCALE = 4;

    public function __construct(public string $percentage)
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $percentage)) {
            throw new InvalidArgumentException("TaxRate percentage inválido: {$percentage}");
        }
    }

    public static function fromPercentage(float|int|string $percentage): self
    {
        $str = is_string($percentage)
            ? $percentage
            : number_format((float) $percentage, self::SCALE, '.', '');

        return new self($str);
    }

    public static function zero(): self
    {
        return new self('0.0000');
    }

    /**
     * Devuelve el factor decimal para multiplicar (21 % → "0.21").
     */
    public function asDecimal(): string
    {
        return bcdiv($this->percentage, '100', self::SCALE + 4);
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->percentage, $other->percentage, self::SCALE));
    }

    public function isZero(): bool
    {
        return bccomp($this->percentage, '0', self::SCALE) === 0;
    }

    public function jsonSerialize(): array
    {
        return [
            'percentage' => $this->percentage,
        ];
    }

    public function __toString(): string
    {
        return "{$this->percentage}%";
    }
}
