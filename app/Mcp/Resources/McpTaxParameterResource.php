<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxParameter;

/**
 * Parámetro fiscal anual (mínimo personal, tope SS, cuotas autónomos…).
 * El `value` es JSON nativo — puede ser número, objeto o array según
 * el `key`.
 *
 * @mixin TaxParameter
 */
class McpTaxParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'year' => (int) $this->year,
            'region_code' => $this->region_code,
            'key' => $this->key,
            'value' => $this->value,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'notes' => $this->notes,
            'source_url' => $this->source_url,
        ];
    }
}
