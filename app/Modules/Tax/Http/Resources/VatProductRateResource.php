<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\VatProductRate;

/**
 * @mixin VatProductRate
 */
class VatProductRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'activity_code' => $this->activity_code,
            'keyword' => $this->keyword,
            'rate_type' => $this->rate_type,
            'rate' => $this->rate,
            'description' => $this->description,
            'source_url' => $this->source_url,
        ];
    }
}
