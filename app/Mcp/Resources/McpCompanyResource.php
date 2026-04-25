<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Company;

/**
 * Vista compacta de una empresa adjudicataria. El Tool decora con
 * `withCount('awards')` y `withSum('awards', 'amount')` o equivalente
 * (`awards_count`, `awards_sum_amount`).
 *
 * Marca `suspect_amount` cuando el agregado supera 1.000.000.000 €
 * (umbral típico de erratas en feed PLACSP).
 *
 * @mixin Company
 */
class McpCompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $awardsCount = isset($this->awards_count) ? (int) $this->awards_count : null;
        $totalAwarded = isset($this->awards_sum_amount) ? (float) $this->awards_sum_amount : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'awards_count' => $awardsCount,
            'total_awarded' => $totalAwarded,
            'suspect_amount' => $totalAwarded !== null && $totalAwarded >= 1_000_000_000.0,
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/companies/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/empresas/{$this->id}",
        ];
    }
}
