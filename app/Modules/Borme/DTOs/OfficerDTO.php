<?php

namespace Modules\Borme\DTOs;

use Modules\Borme\Enums\OfficerAction;
use Modules\Borme\Enums\OfficerRole;

class OfficerDTO
{
    public function __construct(
        public readonly OfficerRole $role,
        public readonly OfficerAction $action,
        public readonly string $kind,                 // 'person' | 'company'
        public readonly string $nameRaw,
        public readonly string $nameNormalized,
        public readonly ?string $representativeNameRaw = null,
    ) {}

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'action' => $this->action->value,
            'kind' => $this->kind,
            'name_raw' => $this->nameRaw,
            'name_normalized' => $this->nameNormalized,
            'representative_name_raw' => $this->representativeNameRaw,
        ];
    }
}
