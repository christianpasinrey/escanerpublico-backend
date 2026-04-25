<?php

namespace Modules\Tax\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Código de régimen tributario. Validado contra catálogo conocido.
 * El catálogo completo vive en BD (tabla tax_regimes); aquí mantenemos
 * la lista de códigos válidos para validación temprana sin tocar BD.
 */
final readonly class RegimeCode implements JsonSerializable, Stringable
{
    public const SCOPE_IRPF = 'irpf';

    public const SCOPE_IVA = 'iva';

    public const SCOPE_IS = 'is';

    public const SCOPE_SS = 'ss';

    /**
     * @var array<string, string> code => scope
     */
    private const KNOWN_REGIMES = [
        // IRPF
        'EDN' => self::SCOPE_IRPF,
        'EDS' => self::SCOPE_IRPF,
        'EO' => self::SCOPE_IRPF,
        'AR' => self::SCOPE_IRPF,
        'ASALARIADO_GEN' => self::SCOPE_IRPF,

        // IVA
        'IVA_GEN' => self::SCOPE_IVA,
        'IVA_CAJA' => self::SCOPE_IVA,
        'IVA_SIMPLE' => self::SCOPE_IVA,
        'IVA_RE' => self::SCOPE_IVA,
        'IVA_REAGP' => self::SCOPE_IVA,
        'IVA_REBU' => self::SCOPE_IVA,
        'IVA_AAVV' => self::SCOPE_IVA,
        'IVA_ISP' => self::SCOPE_IVA,
        'IVA_OIC' => self::SCOPE_IVA,
        'IVA_OSS' => self::SCOPE_IVA,
        'IVA_EXENTO' => self::SCOPE_IVA,

        // IS
        'IS_GEN' => self::SCOPE_IS,
        'IS_ERD' => self::SCOPE_IS,
        'IS_MICRO' => self::SCOPE_IS,
        'IS_STARTUP' => self::SCOPE_IS,
        'IS_COOP' => self::SCOPE_IS,
        'IS_SOCIMI' => self::SCOPE_IS,
        'IS_SICAV' => self::SCOPE_IS,
        'IS_CONSOL' => self::SCOPE_IS,
        'IS_ESFL' => self::SCOPE_IS,

        // SS
        'RG' => self::SCOPE_SS,
        'RETA' => self::SCOPE_SS,
        'AGRARIO' => self::SCOPE_SS,
        'HOGAR' => self::SCOPE_SS,
        'MAR' => self::SCOPE_SS,
        'MINERIA' => self::SCOPE_SS,
    ];

    public function __construct(public string $code)
    {
        if (! isset(self::KNOWN_REGIMES[$code])) {
            throw new InvalidArgumentException("RegimeCode desconocido: {$code}");
        }
    }

    public static function fromString(string $code): self
    {
        return new self($code);
    }

    public function scope(): string
    {
        return self::KNOWN_REGIMES[$this->code];
    }

    public function isIrpf(): bool
    {
        return $this->scope() === self::SCOPE_IRPF;
    }

    public function isIva(): bool
    {
        return $this->scope() === self::SCOPE_IVA;
    }

    public function isIs(): bool
    {
        return $this->scope() === self::SCOPE_IS;
    }

    public function isSs(): bool
    {
        return $this->scope() === self::SCOPE_SS;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::KNOWN_REGIMES;
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'scope' => $this->scope(),
        ];
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
