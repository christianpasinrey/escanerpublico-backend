<?php

namespace Modules\Tax\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Número de Identificación Fiscal español: DNI, NIE o NIF de persona jurídica.
 * Validación con dígito de control. Inmutable.
 */
final readonly class Nif implements JsonSerializable, Stringable
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    private const NIE_PREFIXES = ['X', 'Y', 'Z'];

    private const CIF_LETTERS = 'JABCDEFGHI';

    private const CIF_VALID_FIRST = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'N', 'P', 'Q', 'R', 'S', 'U', 'V', 'W'];

    public function __construct(public string $value)
    {
        $upper = strtoupper(trim($value));

        if (! self::isValid($upper)) {
            throw new InvalidArgumentException("NIF inválido: {$value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public static function isValid(string $value): bool
    {
        $value = strtoupper(trim($value));

        if (preg_match('/^\d{8}[A-Z]$/', $value)) {
            return self::validateDni($value);
        }

        if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $value)) {
            return self::validateNie($value);
        }

        if (preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[A-J0-9]$/', $value)) {
            return self::validateCif($value);
        }

        return false;
    }

    public function isPersonal(): bool
    {
        return preg_match('/^\d/', $this->value) === 1
            || in_array($this->value[0], self::NIE_PREFIXES, true);
    }

    public function isCompany(): bool
    {
        return ! $this->isPersonal();
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function validateDni(string $dni): bool
    {
        $number = (int) substr($dni, 0, 8);
        $letter = substr($dni, 8, 1);

        return self::DNI_LETTERS[$number % 23] === $letter;
    }

    private static function validateNie(string $nie): bool
    {
        $prefixIndex = array_search($nie[0], self::NIE_PREFIXES, true);
        $numericPart = $prefixIndex.substr($nie, 1, 7);
        $letter = substr($nie, 8, 1);

        return self::DNI_LETTERS[((int) $numericPart) % 23] === $letter;
    }

    private static function validateCif(string $cif): bool
    {
        $first = $cif[0];

        if (! in_array($first, self::CIF_VALID_FIRST, true)) {
            return false;
        }

        $digits = substr($cif, 1, 7);
        $control = substr($cif, 8, 1);

        $sumOdd = 0;
        for ($i = 0; $i < 7; $i += 2) {
            $double = ((int) $digits[$i]) * 2;
            $sumOdd += intdiv($double, 10) + ($double % 10);
        }

        $sumEven = 0;
        for ($i = 1; $i < 7; $i += 2) {
            $sumEven += (int) $digits[$i];
        }

        $total = $sumOdd + $sumEven;
        $controlDigit = (10 - ($total % 10)) % 10;

        if (in_array($first, ['A', 'B', 'E', 'H'], true)) {
            return $control === (string) $controlDigit;
        }

        if (in_array($first, ['K', 'P', 'Q', 'S'], true)) {
            return $control === self::CIF_LETTERS[$controlDigit];
        }

        return $control === (string) $controlDigit
            || $control === self::CIF_LETTERS[$controlDigit];
    }
}
