<?php

namespace Modules\Legislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Legislation\Models\LegislationNorm;

/**
 * @mixin LegislationNorm
 */
class LegislationNormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'ambito_code' => $this->ambito_code,
            'ambito_text' => $this->ambito_text,
            'departamento_code' => $this->departamento_code,
            'departamento_text' => $this->departamento_text,
            'rango_code' => $this->rango_code,
            'rango_text' => $this->rango_text,
            'numero_oficial' => $this->numero_oficial,
            'titulo' => $this->titulo,
            'fecha_disposicion' => $this->fecha_disposicion?->toDateString(),
            'fecha_publicacion' => $this->fecha_publicacion?->toDateString(),
            'fecha_vigencia' => $this->fecha_vigencia?->toDateString(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toIso8601String(),
            'vigencia_agotada' => (bool) $this->vigencia_agotada,
            'estado_consolidacion_code' => $this->estado_consolidacion_code,
            'estado_consolidacion_text' => $this->estado_consolidacion_text,
            'url_eli' => $this->url_eli,
            'url_html_consolidada' => $this->url_html_consolidada,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
