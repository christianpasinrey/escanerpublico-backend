<?php

namespace Modules\Officials\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Legislation\Http\Resources\BoeItemResource;
use Modules\Officials\Models\Appointment;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'cargo' => $this->cargo,
            'effective_date' => $this->effective_date?->toDateString(),
            'public_official_id' => $this->public_official_id,
            'boe_item_id' => $this->boe_item_id,
            'organization_id' => $this->organization_id,
            'public_official' => PublicOfficialResource::make($this->whenLoaded('publicOfficial')),
            'boe_item' => BoeItemResource::make($this->whenLoaded('boeItem')),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
