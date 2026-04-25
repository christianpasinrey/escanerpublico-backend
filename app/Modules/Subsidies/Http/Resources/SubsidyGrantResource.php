<?php

namespace Modules\Subsidies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Http\Resources\CompanyResource;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Subsidies\Models\SubsidyGrant;

/**
 * @mixin SubsidyGrant
 */
class SubsidyGrantResource extends JsonResource
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
            'cod_concesion' => $this->cod_concesion,
            'beneficiario_raw' => $this->beneficiario_raw,
            'beneficiario_nif' => $this->beneficiario_nif,
            'beneficiario_name' => $this->beneficiario_name,
            'grant_date' => $this->grant_date?->toDateString(),
            'amount' => $this->amount,
            'ayuda_equivalente' => $this->ayuda_equivalente,
            'instrumento' => $this->instrumento,
            'url_br' => $this->url_br,
            'tiene_proyecto' => (bool) $this->tiene_proyecto,
            'fecha_alta' => $this->fecha_alta?->toDateString(),
            'external_call_id' => $this->external_call_id,
            'call' => SubsidyCallResource::make($this->whenLoaded('call')),
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
