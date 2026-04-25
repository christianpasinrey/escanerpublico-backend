<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxBracket;

/**
 * Tramo de escala progresiva (IRPF general, ahorro, retención, autónomos, IS…).
 *
 * @mixin TaxBracket
 */
class McpTaxBracketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'year' => (int) $this->year,
            'scope' => $this->scope,
            'region_code' => $this->region_code,
            'type' => $this->type,
            'from_amount' => (float) $this->from_amount,
            'to_amount' => $this->to_amount !== null ? (float) $this->to_amount : null,
            'rate' => (float) $this->rate,
            'fixed_amount' => $this->fixed_amount !== null ? (float) $this->fixed_amount : null,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'source_url' => $this->source_url,
        ];
    }
}
