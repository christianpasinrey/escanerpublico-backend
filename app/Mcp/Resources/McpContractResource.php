<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Contract;

/**
 * Representación compacta de un contrato PLACSP optimizada para consumo
 * por agentes (MCP). Diferente del ApiResource público — aquí se prioriza
 * concisión, citación de fuente y avisos sobre erratas (suspect_amount)
 * sobre completitud.
 *
 * @mixin Contract
 */
class McpContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'expediente' => $this->expediente,
            'summary' => $this->description_summary,
            'status' => $this->status_code,
            'organization_id' => $this->organization_id,
            'updated_at' => $this->snapshot_updated_at?->toIso8601String(),
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/contracts/{$this->external_id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/contratos/{$this->external_id}",
        ];
    }
}
