<?php

namespace Modules\Tax\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Resolución de plazos de presentación de obligaciones tributarias.
 *
 * Representa una "regla" expresada como triggers (mes y día opcional) y
 * permite materializar las fechas concretas para un año dado.
 *
 * Triggers comunes:
 *  - quarterly:   abril, julio, octubre, enero (siguiente). Día tope 20 (30 para 4T).
 *  - is_pf:       abril, octubre, diciembre. Día tope 20.
 *  - irpf_annual: 1 abril → 30 junio del ejercicio siguiente.
 *  - is_annual:   25 días siguientes a 6 meses tras cierre. Asumimos cierre 31-12.
 *
 * Ejemplos uso:
 *
 *   $rule = ObligationDeadline::quarterly();
 *   $rule->datesFor(2025);
 *   // → ['2025-04-20', '2025-07-20', '2025-10-20', '2026-01-30']
 */
final readonly class ObligationDeadline implements JsonSerializable
{
    public const PRESET_QUARTERLY = 'quarterly';

    public const PRESET_IS_FRACTIONAL = 'is_fractional';

    public const PRESET_IRPF_ANNUAL = 'irpf_annual';

    public const PRESET_IS_ANNUAL = 'is_annual';

    public const PRESET_VAT_ANNUAL = 'vat_annual';

    public const PRESET_AR_ANNUAL = 'ar_annual';

    public const PRESET_OSS_QUARTERLY = 'oss_quarterly';

    public const PRESET_INTRACOM_MONTHLY = 'intracom_monthly';

    /**
     * @param  list<array{month: int, day: int, year_offset?: int, label?: string}>  $triggers
     */
    public function __construct(
        public string $preset,
        public array $triggers,
        public string $description,
    ) {
        if ($preset === '') {
            throw new InvalidArgumentException('preset no puede estar vacío.');
        }
        if ($triggers === []) {
            throw new InvalidArgumentException('triggers no puede estar vacío.');
        }
    }

    public static function quarterly(): self
    {
        return new self(
            preset: self::PRESET_QUARTERLY,
            triggers: [
                ['month' => 4, 'day' => 20, 'year_offset' => 0, 'label' => 'Trimestre 1'],
                ['month' => 7, 'day' => 20, 'year_offset' => 0, 'label' => 'Trimestre 2'],
                ['month' => 10, 'day' => 20, 'year_offset' => 0, 'label' => 'Trimestre 3'],
                ['month' => 1, 'day' => 30, 'year_offset' => 1, 'label' => 'Trimestre 4'],
            ],
            description: 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
        );
    }

    public static function isFractional(): self
    {
        return new self(
            preset: self::PRESET_IS_FRACTIONAL,
            triggers: [
                ['month' => 4, 'day' => 20, 'year_offset' => 0, 'label' => '1P (abril)'],
                ['month' => 10, 'day' => 20, 'year_offset' => 0, 'label' => '2P (octubre)'],
                ['month' => 12, 'day' => 20, 'year_offset' => 0, 'label' => '3P (diciembre)'],
            ],
            description: 'Días 1-20 de abril, octubre y diciembre.',
        );
    }

    public static function irpfAnnual(): self
    {
        return new self(
            preset: self::PRESET_IRPF_ANNUAL,
            triggers: [
                ['month' => 6, 'day' => 30, 'year_offset' => 1, 'label' => 'Renta'],
            ],
            description: 'Del primer día hábil de abril al 30 de junio del ejercicio siguiente.',
        );
    }

    public static function isAnnual(): self
    {
        return new self(
            preset: self::PRESET_IS_ANNUAL,
            triggers: [
                ['month' => 7, 'day' => 25, 'year_offset' => 1, 'label' => 'IS anual'],
            ],
            description: 'Dentro de los 25 días naturales siguientes a los 6 meses posteriores al cierre del ejercicio (cierre 31-12 → 25 julio).',
        );
    }

    public static function vatAnnual(): self
    {
        return new self(
            preset: self::PRESET_VAT_ANNUAL,
            triggers: [
                ['month' => 1, 'day' => 30, 'year_offset' => 1, 'label' => 'Resumen anual'],
            ],
            description: 'Días 1-30 de enero del año siguiente.',
        );
    }

    public static function arAnnual(): self
    {
        return new self(
            preset: self::PRESET_AR_ANNUAL,
            triggers: [
                ['month' => 1, 'day' => 31, 'year_offset' => 1, 'label' => 'Atribución de rentas'],
            ],
            description: 'Mes de enero del año siguiente al ejercicio (días 1-31).',
        );
    }

    public static function ossQuarterly(): self
    {
        return new self(
            preset: self::PRESET_OSS_QUARTERLY,
            triggers: [
                ['month' => 4, 'day' => 30, 'year_offset' => 0, 'label' => 'OSS T1'],
                ['month' => 7, 'day' => 30, 'year_offset' => 0, 'label' => 'OSS T2'],
                ['month' => 10, 'day' => 30, 'year_offset' => 0, 'label' => 'OSS T3'],
                ['month' => 1, 'day' => 30, 'year_offset' => 1, 'label' => 'OSS T4'],
            ],
            description: 'Días 1-30 del mes siguiente al fin del trimestre.',
        );
    }

    public static function intracomMonthly(): self
    {
        $triggers = [];
        for ($m = 1; $m <= 12; $m++) {
            $nextMonth = $m === 12 ? 1 : $m + 1;
            $yearOffset = $m === 12 ? 1 : 0;
            $triggers[] = [
                'month' => $nextMonth,
                'day' => 20,
                'year_offset' => $yearOffset,
                'label' => sprintf('Mes %02d', $m),
            ];
        }

        return new self(
            preset: self::PRESET_INTRACOM_MONTHLY,
            triggers: $triggers,
            description: 'Días 1-20 del mes siguiente al periodo (modelo 349).',
        );
    }

    public static function fromPreset(string $preset): self
    {
        return match ($preset) {
            self::PRESET_QUARTERLY => self::quarterly(),
            self::PRESET_IS_FRACTIONAL => self::isFractional(),
            self::PRESET_IRPF_ANNUAL => self::irpfAnnual(),
            self::PRESET_IS_ANNUAL => self::isAnnual(),
            self::PRESET_VAT_ANNUAL => self::vatAnnual(),
            self::PRESET_AR_ANNUAL => self::arAnnual(),
            self::PRESET_OSS_QUARTERLY => self::ossQuarterly(),
            self::PRESET_INTRACOM_MONTHLY => self::intracomMonthly(),
            default => throw new InvalidArgumentException("Preset desconocido: {$preset}"),
        };
    }

    /**
     * Materializa las fechas concretas para un ejercicio dado.
     *
     * @return list<array{date: string, label: string}>
     */
    public function datesFor(int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Año fuera de rango razonable: {$year}");
        }

        $dates = [];
        foreach ($this->triggers as $t) {
            $date = (new DateTimeImmutable)
                ->setDate($year + ($t['year_offset'] ?? 0), $t['month'], $t['day']);

            $dates[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $t['label'] ?? '',
            ];
        }

        return $dates;
    }

    public function jsonSerialize(): array
    {
        return [
            'preset' => $this->preset,
            'description' => $this->description,
            'triggers' => $this->triggers,
        ];
    }
}
