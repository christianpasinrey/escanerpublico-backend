<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxRegimeCompatibility;

/**
 * @mixin TaxRegimeCompatibility
 */
class TaxRegimeCompatibilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'compatibility' => $this->compatibility,
            'notes' => $this->notes,
            'regime_a' => $this->whenLoaded('regimeA', fn () => [
                'code' => $this->regimeA->code,
                'name' => $this->regimeA->name,
                'scope' => $this->regimeA->scope,
            ]),
            'regime_b' => $this->whenLoaded('regimeB', fn () => [
                'code' => $this->regimeB->code,
                'name' => $this->regimeB->name,
                'scope' => $this->regimeB->scope,
            ]),
        ];
    }
}
