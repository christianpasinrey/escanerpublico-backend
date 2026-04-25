<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Company;

/**
 * Vista detallada de una empresa adjudicataria. Incluye top-N awards
 * cargados por el Tool y, si está disponible, un resumen de organismos
 * cruzados (`organisms_breakdown`) preparado por el Tool vía agregación.
 *
 * @mixin Company
 */
class McpCompanyDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $awardsCount = isset($this->awards_count) ? (int) $this->awards_count : null;
        $totalAwarded = isset($this->awards_sum_amount) ? (float) $this->awards_sum_amount : null;

        $topAwards = $this->whenLoaded('awards', fn () => $this->awards->map(fn ($a) => [
            'id' => $a->id,
            'amount' => $a->amount !== null ? (float) $a->amount : null,
            'amount_without_tax' => $a->amount_without_tax !== null ? (float) $a->amount_without_tax : null,
            'award_date' => $a->award_date?->toDateString(),
            'formalization_date' => $a->formalization_date?->toDateString(),
            'sme_awarded' => (bool) $a->sme_awarded,
            'contract_lot_id' => $a->contract_lot_id,
            'suspect_amount' => $a->amount !== null && (float) $a->amount >= 1_000_000_000.0,
        ])->all());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'identifier' => $this->identifier,
            'awards_count' => $awardsCount,
            'total_awarded' => $totalAwarded,
            'suspect_amount' => $totalAwarded !== null && $totalAwarded >= 1_000_000_000.0,
            'top_awards' => $topAwards,
            'organisms_breakdown' => $this->when(
                isset($this->organisms_breakdown),
                fn () => $this->organisms_breakdown,
            ),
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/companies/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/empresas/{$this->id}",
        ];
    }
}
