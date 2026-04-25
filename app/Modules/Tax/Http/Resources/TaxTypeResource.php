<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxType;

/**
 * @mixin TaxType
 */
class TaxTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'scope' => $this->scope instanceof Scope ? $this->scope->value : $this->scope,
            'scope_label' => $this->scope instanceof Scope ? $this->scope->label() : null,
            'levy_type' => $this->levy_type instanceof LevyType ? $this->levy_type->value : $this->levy_type,
            'levy_type_label' => $this->levy_type instanceof LevyType ? $this->levy_type->label() : null,
            'name' => $this->name,
            'base_law_url' => $this->base_law_url,
            'region_code' => $this->region_code,
            'municipality_id' => $this->municipality_id,
            'editorial_md' => $this->editorial_md,
            'rates_count' => $this->whenCounted('rates'),
            'rates' => TaxRateResource::collection($this->whenLoaded('rates')),
        ];
    }
}
