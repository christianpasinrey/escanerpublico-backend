<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\ContractModification;

/**
 * @mixin ContractModification
 */
class ModificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'issue_date' => $this->issue_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'description' => $this->description,
            'amount_delta' => $this->amount_delta,
            'new_end_date' => $this->new_end_date?->toDateString(),
        ];
    }
}
