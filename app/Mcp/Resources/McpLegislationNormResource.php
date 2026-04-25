<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Legislation\Models\LegislationNorm;

/**
 * Disposición publicada en el BOE. Lleva el ELI europeo (url_eli) y
 * la URL a la versión consolidada del BOE (url_html_consolidada).
 *
 * @mixin LegislationNorm
 */
class McpLegislationNormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'eli' => $this->external_id,
            'section' => $this->ambito_text,
            'section_code' => $this->ambito_code,
            'type' => $this->rango_text,
            'type_code' => $this->rango_code,
            'numero_oficial' => $this->numero_oficial,
            'title' => $this->titulo,
            'organism' => $this->departamento_text,
            'organization_id' => $this->organization_id,
            'fecha_disposicion' => $this->fecha_disposicion?->toDateString(),
            'fecha_publicacion' => $this->fecha_publicacion?->toDateString(),
            'fecha_vigencia' => $this->fecha_vigencia?->toDateString(),
            'vigencia_agotada' => (bool) $this->vigencia_agotada,
            'estado_consolidacion' => $this->estado_consolidacion_text,
            'boe_url' => $this->url_html_consolidada ?? $this->url_eli,
            'eli_url' => $this->url_eli,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/legislation/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/legislacion/{$this->external_id}",
        ];
    }
}
