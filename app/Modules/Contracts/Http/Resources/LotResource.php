<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Contracts\Models\ContractLot
 */
class LotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_number' => $this->lot_number,
            'title' => $this->title,
            'description' => $this->description,
            'tipo_contrato_code' => $this->tipo_contrato_code,
            'cpv_codes' => $this->cpv_codes,
            'budget_with_tax' => $this->budget_with_tax,
            'budget_without_tax' => $this->budget_without_tax,
            'estimated_value' => $this->estimated_value,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'nuts_code' => $this->nuts_code,
            'lugar_ejecucion' => $this->lugar_ejecucion,

            'contract' => ContractResource::make($this->whenLoaded('contract')),
            'awards' => AwardResource::collection($this->whenLoaded('awards')),
            'criteria' => CriterionResource::collection($this->whenLoaded('criteria')),
        ];
    }
}
