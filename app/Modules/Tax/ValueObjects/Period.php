<?php

namespace Modules\Tax\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Período de validez (inclusivo en ambos extremos).
 * Ambos extremos pueden ser nulos: null = "desde siempre" / "hasta siempre".
 */
final readonly class Period implements JsonSerializable, Stringable
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {
        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            throw new InvalidArgumentException(
                "Period inválido: from ({$from->toDateString()}) > to ({$to->toDateString()})"
            );
        }
    }

    public static function open(): self
    {
        return new self(null, null);
    }

    public static function fromYear(FiscalYear $year): self
    {
        return new self($year->start(), $year->end());
    }

    public function contains(CarbonImmutable $date): bool
    {
        if ($this->from !== null && $date->lessThan($this->from)) {
            return false;
        }

        if ($this->to !== null && $date->greaterThan($this->to)) {
            return false;
        }

        return true;
    }

    public function overlaps(self $other): bool
    {
        if ($this->to !== null && $other->from !== null && $this->to->lessThan($other->from)) {
            return false;
        }

        if ($other->to !== null && $this->from !== null && $other->to->lessThan($this->from)) {
            return false;
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
        ];
    }

    public function __toString(): string
    {
        $from = $this->from?->toDateString() ?? '−∞';
        $to = $this->to?->toDateString() ?? '+∞';

        return "[{$from} … {$to}]";
    }
}
