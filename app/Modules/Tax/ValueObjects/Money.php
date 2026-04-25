<?php

namespace Modules\Tax\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Money con aritmética bcmath (precisión decimal exacta).
 * Inmutable. Solo EUR en MVP — el campo currency queda preparado para futuro.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public const SCALE = 2;

    public function __construct(
        public string $amount,
        public string $currency = 'EUR',
    ) {
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Money amount inválido: {$amount}");
        }
    }

    public static function zero(string $currency = 'EUR'): self
    {
        return new self('0.00', $currency);
    }

    public static function fromFloat(float|int $amount, string $currency = 'EUR'): self
    {
        return new self(number_format((float) $amount, self::SCALE, '.', ''), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcadd($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcsub($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function multiply(string|float|int $factor): self
    {
        $factorStr = is_string($factor) ? $factor : number_format((float) $factor, 8, '.', '');

        return new self(bcmul($this->amount, $factorStr, self::SCALE), $this->currency);
    }

    public function divide(string|float|int $divisor): self
    {
        $divisorStr = is_string($divisor) ? $divisor : number_format((float) $divisor, 8, '.', '');

        if (bccomp($divisorStr, '0', 8) === 0) {
            throw new InvalidArgumentException('División por cero en Money');
        }

        return new self(bcdiv($this->amount, $divisorStr, self::SCALE), $this->currency);
    }

    public function applyRate(TaxRate $rate): self
    {
        return $this->multiply($rate->asDecimal());
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, self::SCALE);
    }

    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function __toString(): string
    {
        return "{$this->amount} {$this->currency}";
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Money currency mismatch: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
