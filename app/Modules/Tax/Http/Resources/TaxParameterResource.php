<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxParameter;

/**
 * @mixin TaxParameter
 */
class TaxParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'region_code' => $this->region_code,
            'key' => $this->key,
            'value' => $this->value,
            'source_url' => $this->source_url,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
