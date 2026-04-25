<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Officials\Models\PublicOfficial;

/**
 * Cargo público (extraído de la Sección II.A del BOE). El `cargo` y el
 * `organism` actuales se inyectan desde el Tool — el modelo persiste
 * únicamente la persona; la trayectoria vive en `appointments`.
 *
 * @mixin PublicOfficial
 */
class McpOfficialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'persona' => $this->full_name,
            'normalized_name' => $this->normalized_name,
            'honorific' => $this->honorific,
            'cargo' => $this->when(isset($this->latest_cargo), fn () => $this->latest_cargo),
            'organism' => $this->when(isset($this->latest_organism), fn () => $this->latest_organism),
            'appointments_count' => (int) $this->appointments_count,
            'first_appointment_date' => $this->first_appointment_date?->toDateString(),
            'ultima_appointment_date' => $this->last_event_date?->toDateString(),
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/officials/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/cargos/{$this->id}",
        ];
    }
}
