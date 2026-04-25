<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxRegime;

/**
 * @mixin TaxRegime
 */
class TaxRegimeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'editorial_md' => $this->editorial_md,
            'obligations' => TaxRegimeObligationResource::collection($this->whenLoaded('obligations')),
            'compatibilities' => $this->when(
                $this->relationLoaded('compatibleRegimes'),
                fn () => $this->compatibleRegimes->map(fn ($r) => [
                    'code' => $r->code,
                    'name' => $r->name,
                    'scope' => $r->scope,
                    'compatibility' => $r->pivot->compatibility ?? null,
                    'notes' => $r->pivot->notes ?? null,
                ])->values(),
            ),
        ];
    }
}
