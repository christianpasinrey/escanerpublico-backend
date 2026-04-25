<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subsidies\Models\SubsidyCall;

/**
 * Convocatoria de subvención (BDNS). Descripción truncada a 240 chars
 * para conservar tokens — el detalle completo se obtiene vía detail_url.
 *
 * @mixin SubsidyCall
 */
class McpSubsidyCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $description = (string) ($this->description ?? '');
        if (mb_strlen($description) > 240) {
            $description = rtrim(mb_substr($description, 0, 240)).'…';
        }

        return [
            'id' => $this->id,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'numero_convocatoria' => $this->numero_convocatoria,
            'organization_id' => $this->organization_id,
            'description' => $description,
            'reception_date' => $this->reception_date?->toDateString(),
            'nivel1' => $this->nivel1,
            'nivel2' => $this->nivel2,
            'nivel3' => $this->nivel3,
            'is_mrr' => (bool) $this->is_mrr,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/subsidies/calls/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/subvenciones/convocatorias/{$this->id}",
            'source_url' => "https://www.infosubvenciones.es/bdnstrans/GE/es/convocatoria/{$this->external_id}",
        ];
    }
}
