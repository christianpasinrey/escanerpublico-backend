<?php

namespace Modules\Tax\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tax\Models\TaxRegimeObligation;

/**
 * @mixin TaxRegimeObligation
 */
class TaxRegimeObligationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'model_code' => $this->model_code,
            'periodicity' => $this->periodicity,
            'deadline_rule' => $this->deadline_rule,
            'description' => $this->description,
            'electronic_required' => (bool) $this->electronic_required,
            'certificate_required' => (bool) $this->certificate_required,
            'draft_available' => (bool) $this->draft_available,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'source_url' => $this->source_url,
        ];
    }
}
