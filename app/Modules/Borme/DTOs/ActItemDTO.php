<?php

namespace Modules\Borme\DTOs;

use Modules\Borme\Enums\ActType;

class ActItemDTO
{
    public function __construct(
        public readonly ActType $actType,
        public readonly array $payload,
        public readonly ?string $effectiveDate = null,
    ) {}

    public function toArray(): array
    {
        return [
            'act_type' => $this->actType->value,
            'payload' => $this->payload,
            'effective_date' => $this->effectiveDate,
        ];
    }
}
