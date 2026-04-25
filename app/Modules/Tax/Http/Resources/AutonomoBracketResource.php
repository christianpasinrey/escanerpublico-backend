<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\AutonomoBracket;

/**
 * @mixin AutonomoBracket
 */
class AutonomoBracketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'bracket_number' => $this->bracket_number,
            'from_yield' => $this->from_yield,
            'to_yield' => $this->to_yield,
            'base_min' => $this->base_min,
            'base_max' => $this->base_max,
            'monthly_quota_min' => $this->monthly_quota_min,
            'monthly_quota_max' => $this->monthly_quota_max,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'source_url' => $this->source_url,
        ];
    }
}
