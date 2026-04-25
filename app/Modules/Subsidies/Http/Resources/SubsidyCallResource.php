<?php

namespace Modules\Subsidies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Subsidies\Models\SubsidyCall;

/**
 * @mixin SubsidyCall
 */
class SubsidyCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'numero_convocatoria' => $this->numero_convocatoria,
            'description' => $this->description,
            'description_cooficial' => $this->description_cooficial,
            'reception_date' => $this->reception_date?->toDateString(),
            'nivel1' => $this->nivel1,
            'nivel2' => $this->nivel2,
            'nivel3' => $this->nivel3,
            'codigo_invente' => $this->codigo_invente,
            'is_mrr' => (bool) $this->is_mrr,
            'grants_count' => $this->whenCounted('grants'),
            'grants_sum_amount' => $this->grants_sum_amount ?? null,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'grants' => SubsidyGrantResource::collection($this->whenLoaded('grants')),
        ];
    }
}
