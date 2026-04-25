<?php

namespace Modules\Officials\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Officials\Models\PublicOfficial;

/**
 * @mixin PublicOfficial
 */
class PublicOfficialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'normalized_name' => $this->normalized_name,
            'honorific' => $this->honorific,
            'appointments_count' => $this->appointments_count,
            'first_appointment_date' => $this->first_appointment_date?->toDateString(),
            'last_event_date' => $this->last_event_date?->toDateString(),
            'appointments' => AppointmentResource::collection($this->whenLoaded('appointments')),
        ];
    }
}
