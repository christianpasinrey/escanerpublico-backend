<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Organization;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'nif' => $this->nif,
            'type_code' => $this->type_code,
            'activity_code' => $this->activity_code,
            'buyer_profile_uri' => $this->buyer_profile_uri,
            'hierarchy' => $this->hierarchy,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'contracts' => ContractResource::collection($this->whenLoaded('contracts')),
        ];
    }
}
