<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Officials\Models\PublicOfficial;

/**
 * Trayectoria completa de un cargo público. Espera `->load('appointments.boeItem')`
 * para emitir las citas a la disposición original (BOE).
 *
 * @mixin PublicOfficial
 */
class McpOfficialDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $appointments = $this->whenLoaded('appointments', function () {
            return $this->appointments
                ->sortByDesc(fn ($a) => $a->effective_date?->timestamp ?? 0)
                ->values()
                ->map(function ($a) {
                    $boeId = $a->relationLoaded('boeItem') && $a->boeItem !== null
                        ? $a->boeItem->external_id
                        : null;

                    return [
                        'id' => $a->id,
                        'event_type' => $a->event_type,
                        'cargo' => $a->cargo,
                        'effective_date' => $a->effective_date?->toDateString(),
                        'organization_id' => $a->organization_id,
                        'boe_item_id' => $a->boe_item_id,
                        'boe_external_id' => $boeId,
                        'boe_url' => $boeId !== null
                            ? "https://www.boe.es/diario_boe/txt.php?id={$boeId}"
                            : null,
                    ];
                })
                ->all();
        });

        return [
            'id' => $this->id,
            'persona' => $this->full_name,
            'normalized_name' => $this->normalized_name,
            'honorific' => $this->honorific,
            'appointments_count' => (int) $this->appointments_count,
            'first_appointment_date' => $this->first_appointment_date?->toDateString(),
            'last_event_date' => $this->last_event_date?->toDateString(),
            'appointments' => $appointments,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/officials/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/cargos/{$this->id}",
        ];
    }
}
