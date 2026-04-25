<?php

namespace Modules\Tax\DTOs\Calendar;

use JsonSerializable;

/**
 * Una fecha concreta del calendario fiscal con la obligación que la motiva.
 */
final readonly class CalendarEntry implements JsonSerializable
{
    public function __construct(
        public string $date,
        public string $modelCode,
        public string $regimeCode,
        public string $periodicity,
        public string $label,
        public string $description,
        public ?string $sourceUrl = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'model_code' => $this->modelCode,
            'regime_code' => $this->regimeCode,
            'periodicity' => $this->periodicity,
            'label' => $this->label,
            'description' => $this->description,
            'source_url' => $this->sourceUrl,
        ];
    }
}
