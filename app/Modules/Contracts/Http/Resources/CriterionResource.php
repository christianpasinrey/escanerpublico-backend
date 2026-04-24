<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\AwardingCriterion;

/**
 * @mixin AwardingCriterion
 */
class CriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type_code' => $this->type_code,
            'subtype_code' => $this->subtype_code,
            'description' => $this->description,
            'note' => $this->note,
            'weight_numeric' => $this->weight_numeric,
            'sort_order' => $this->sort_order,
        ];
    }
}
