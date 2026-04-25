<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subsidies\Models\SubsidyGrant;

/**
 * Concesión de subvención (BDNS). Cifra `importe` marcada como sospechosa
 * cuando supera 1.000.000.000 € (umbral típico de erratas).
 *
 * @mixin SubsidyGrant
 */
class McpSubsidyGrantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount = $this->amount !== null ? (float) $this->amount : null;

        return [
            'id' => $this->id,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'cod_concesion' => $this->cod_concesion,
            'call_id' => $this->call_id,
            'organization_id' => $this->organization_id,
            'company_id' => $this->company_id,
            'beneficiario_nombre' => $this->beneficiario_name,
            'beneficiario_nif' => $this->beneficiario_nif,
            'importe' => $amount,
            'ayuda_equivalente' => $this->ayuda_equivalente !== null ? (float) $this->ayuda_equivalente : null,
            'instrumento' => $this->instrumento,
            'fecha' => $this->grant_date?->toDateString(),
            'tiene_proyecto' => (bool) $this->tiene_proyecto,
            'suspect_amount' => $amount !== null && $amount >= 1_000_000_000.0,
            'source_url' => $this->url_br,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/subsidies/grants/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/subvenciones/concesiones/{$this->id}",
        ];
    }
}
