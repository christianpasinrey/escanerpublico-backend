<?php

namespace Modules\Tax\DTOs\Calendar;

use JsonSerializable;

/**
 * Calendario fiscal materializado para un régimen y año concretos.
 */
final readonly class ObligationCalendar implements JsonSerializable
{
    /**
     * @param  list<CalendarEntry>  $entries
     */
    public function __construct(
        public string $regimeCode,
        public string $regimeName,
        public string $regimeScope,
        public int $year,
        public array $entries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'regime' => [
                'code' => $this->regimeCode,
                'name' => $this->regimeName,
                'scope' => $this->regimeScope,
            ],
            'year' => $this->year,
            'entries' => $this->entries,
        ];
    }
}
