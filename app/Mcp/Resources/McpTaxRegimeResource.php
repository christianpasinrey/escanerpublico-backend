<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxRegime;

/**
 * Régimen tributario del catálogo (IRPF/IVA/IS/SS).
 *
 * @mixin TaxRegime
 */
class McpTaxRegimeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'scope' => $this->scope,
            'name' => $this->name,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'model_quarterly' => $this->model_quarterly,
            'model_annual' => $this->model_annual,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'legal_reference_url' => $this->legal_reference_url,
        ];
    }
}
