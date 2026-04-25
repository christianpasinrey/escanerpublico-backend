<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Contracts\Models\Company;

/**
 * Vista detallada de una empresa adjudicataria. Incluye top-N awards
 * cargados por el Tool y, si está disponible, un resumen de organismos
 * cruzados (`organisms_breakdown_raw`) preparado por el Tool vía agregación.
 * La transformación fila-a-fila ocurre aquí, no en el handle() del Tool.
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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'identifier' => $this->identifier,
            'awards_count' => $awardsCount,
            'total_awarded' => $totalAwarded,
            'suspect_amount' => $totalAwarded !== null && $totalAwarded >= 1_000_000_000.0,
            'top_awards' => $this->whenLoaded('awards', fn () => $this->shapeTopAwards($this->awards)),
            'organisms_breakdown' => $this->when(
                isset($this->organisms_breakdown_raw),
                fn () => $this->shapeOrganisms($this->organisms_breakdown_raw),
            ),
            'detail_url' => "https://app.gobtracker.tailor-bytes.com/api/v1/companies/{$this->id}",
            'public_url' => "https://gobtracker.tailor-bytes.com/empresas/{$this->id}",
        ];
    }

    /**
     * @param  iterable<int, mixed>  $awards
     * @return list<array<string, mixed>>
     */
    private function shapeTopAwards(iterable $awards): array
    {
        $out = [];
        foreach ($awards as $a) {
            $amount = $a->amount !== null ? (float) $a->amount : null;
            $out[] = [
                'id' => $a->id,
                'amount' => $amount,
                'amount_without_tax' => $a->amount_without_tax !== null ? (float) $a->amount_without_tax : null,
                'award_date' => $a->award_date?->toDateString(),
                'formalization_date' => $a->formalization_date?->toDateString(),
                'sme_awarded' => (bool) $a->sme_awarded,
                'contract_lot_id' => $a->contract_lot_id,
                'suspect_amount' => $amount !== null && $amount >= 1_000_000_000.0,
            ];
        }

        return $out;
    }

    /**
     * @param  iterable<int, object|array<string,mixed>>|Collection  $rows
     * @return list<array<string, mixed>>
     */
    private function shapeOrganisms(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $orgId = is_object($r) ? ($r->organization_id ?? null) : ($r['organization_id'] ?? null);
            $name = is_object($r) ? ($r->name ?? null) : ($r['name'] ?? null);
            $nif = is_object($r) ? ($r->nif ?? null) : ($r['nif'] ?? null);
            $count = is_object($r) ? ($r->awards_count ?? 0) : ($r['awards_count'] ?? 0);
            $total = is_object($r) ? ($r->total ?? 0) : ($r['total'] ?? 0);
            $totalF = (float) $total;
            $out[] = [
                'organization_id' => (int) $orgId,
                'name' => $name,
                'nif' => $nif,
                'awards_count' => (int) $count,
                'total' => $totalF,
                'suspect_amount' => $totalF >= 1_000_000_000.0,
            ];
        }

        return $out;
    }
}
