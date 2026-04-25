<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxType;

/**
 * Tipo impositivo del catálogo (IRPF, IVA, IS, IIEE, IBI…).
 *
 * @mixin TaxType
 */
class McpTaxTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'scope' => $this->scope instanceof \BackedEnum ? $this->scope->value : $this->scope,
            'levy_type' => $this->levy_type instanceof \BackedEnum ? $this->levy_type->value : $this->levy_type,
            'name' => $this->name,
            'region_code' => $this->region_code,
            'municipality_id' => $this->municipality_id,
            'base_law_url' => $this->base_law_url,
        ];
    }
}
