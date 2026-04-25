<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxBracket;

/**
 * @mixin TaxBracket
 */
class TaxBracketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'scope' => $this->scope,
            'region_code' => $this->region_code,
            'type' => $this->type,
            'from_amount' => $this->from_amount,
            'to_amount' => $this->to_amount,
            'rate' => $this->rate,
            'fixed_amount' => $this->fixed_amount,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'source_url' => $this->source_url,
        ];
    }
}
