<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Organization;

/**
 * Vista compacta de un organismo contratante (DIR3) para consumo MCP.
 * Usa columnas calculadas inyectadas por el Tool: `contracts_count` (withCount)
 * y `total_amount` (selectRaw / withSum).
 *
 * @mixin Organization
 */
class McpOrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalAmount = isset($this->total_amount) ? (float) $this->total_amount : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'nif' => $this->nif,
            'type_code' => $this->type_code,
            'parent_name' => $this->parent_name,
            'contracts_count' => isset($this->contracts_count) ? (int) $this->contracts_count : null,
            'total_amount' => $totalAmount,
            'suspect_amount' => $totalAmount !== null && $totalAmount >= 1_000_000_000.0,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/organismos/{$this->id}",
        ];
    }
}
