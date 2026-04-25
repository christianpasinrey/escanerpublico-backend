<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\SocialSecurityRate;

/**
 * @mixin SocialSecurityRate
 */
class SocialSecurityRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'regime' => $this->regime,
            'contingency' => $this->contingency,
            'rate_employer' => $this->rate_employer,
            'rate_employee' => $this->rate_employee,
            'base_min' => $this->base_min,
            'base_max' => $this->base_max,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'source_url' => $this->source_url,
            'notes' => $this->notes,
        ];
    }
}
