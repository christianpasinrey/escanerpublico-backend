<?php

namespace Modules\Legislation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Legislation\Models\BoeSummary;

/**
 * @mixin BoeSummary
 */
class BoeSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'identificador' => $this->identificador,
            'fecha_publicacion' => $this->fecha_publicacion?->toDateString(),
            'numero' => $this->numero,
            'url_pdf' => $this->url_pdf,
            'pdf_size_bytes' => $this->pdf_size_bytes,
            'items_count' => $this->whenCounted('items'),
            'items' => BoeItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
