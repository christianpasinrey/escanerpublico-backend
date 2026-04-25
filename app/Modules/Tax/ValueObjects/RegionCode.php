<?php

namespace Modules\Tax\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Código ISO 3166-2:ES de comunidad autónoma (sin prefijo "ES-").
 * Soporta también "STATE" como alias del ámbito estatal (no autonómico).
 */
final readonly class RegionCode implements JsonSerializable, Stringable
{
    public const STATE = 'STATE';

    private const VALID_REGIONS = [
        'AN' => 'Andalucía',
        'AR' => 'Aragón',
        'AS' => 'Asturias',
        'CN' => 'Canarias',
        'CB' => 'Cantabria',
        'CM' => 'Castilla-La Mancha',
        'CL' => 'Castilla y León',
        'CT' => 'Cataluña',
        'EX' => 'Extremadura',
        'GA' => 'Galicia',
        'IB' => 'Islas Baleares',
        'RI' => 'La Rioja',
        'MD' => 'Madrid',
        'MC' => 'Murcia',
        'NC' => 'Navarra',
        'PV' => 'País Vasco',
        'VC' => 'Valenciana',
        'CE' => 'Ceuta',
        'ML' => 'Melilla',
    ];

    public function __construct(public string $code)
    {
        if ($code !== self::STATE && ! isset(self::VALID_REGIONS[$code])) {
            throw new InvalidArgumentException("RegionCode inválido: {$code}");
        }
    }

    public static function state(): self
    {
        return new self(self::STATE);
    }

    public static function fromCode(string $code): self
    {
        return new self(strtoupper($code));
    }

    public function isState(): bool
    {
        return $this->code === self::STATE;
    }

    public function isForal(): bool
    {
        return $this->code === 'PV' || $this->code === 'NC';
    }

    public function name(): string
    {
        return self::VALID_REGIONS[$this->code] ?? 'Estado';
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::VALID_REGIONS;
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name(),
        ];
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
