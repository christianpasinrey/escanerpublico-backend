<?php

namespace Modules\Legislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Legislation\Models\BoeItem;

/**
 * @mixin BoeItem
 */
class BoeItemResource extends JsonResource
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
            'control' => $this->control,
            'seccion_code' => $this->seccion_code,
            'seccion_nombre' => $this->seccion_nombre,
            'departamento_code' => $this->departamento_code,
            'departamento_nombre' => $this->departamento_nombre,
            'epigrafe' => $this->epigrafe,
            'titulo' => $this->titulo,
            'url_pdf' => $this->url_pdf,
            'pdf_size_bytes' => $this->pdf_size_bytes,
            'pagina_inicial' => $this->pagina_inicial,
            'pagina_final' => $this->pagina_final,
            'url_html' => $this->url_html,
            'url_xml' => $this->url_xml,
            'fecha_publicacion' => $this->fecha_publicacion?->toDateString(),
            'summary' => BoeSummaryResource::make($this->whenLoaded('summary')),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
        ];
    }
}
