<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Company;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'awards_count' => $this->whenCounted('awards'),
            'awards_sum_amount' => $this->awards_sum_amount ?? null,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'awards' => AwardResource::collection($this->whenLoaded('awards')),
        ];
    }
}
