<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxRate;

/**
 * @mixin TaxRate
 */
class TaxRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tax_type_id' => $this->tax_type_id,
            'year' => $this->year,
            'region_code' => $this->region_code,
            'rate' => $this->rate !== null ? (float) $this->rate : null,
            'base_min' => $this->base_min !== null ? (float) $this->base_min : null,
            'base_max' => $this->base_max !== null ? (float) $this->base_max : null,
            'fixed_amount' => $this->fixed_amount !== null ? (float) $this->fixed_amount : null,
            'conditions' => $this->conditions,
            'source_url' => $this->source_url,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
        ];
    }
}
