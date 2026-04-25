<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para estadísticas agregadas de un organismo. Recibe un array
 * estructurado preparado por el Tool, no un modelo Eloquent. Por eso no
 * declara `@mixin` — es un wrapper de datos calculados.
 *
 * Estructura esperada del payload:
 * [
 *   'organization_id' => int,
 *   'organization_name' => string,
 *   'volume_by_year' => list<array{year:int, count:int, amount:float}>,
 *   'volume_by_status' => list<array{status:string, count:int, amount:float}>,
 *   'top_companies' => list<array{company_id:int, name:?string, nif:?string, awards_count:int, total:float}>,
 * ]
 */
class McpOrganizationStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = (array) $this->resource;

        $totalsByYear = $data['volume_by_year'] ?? [];
        $hasSuspect = false;
        foreach ($totalsByYear as $row) {
            if (($row['amount'] ?? 0) >= 1_000_000_000.0) {
                $hasSuspect = true;
                break;
            }
        }

        return [
            'organization_id' => $data['organization_id'] ?? null,
            'organization_name' => $data['organization_name'] ?? null,
            'volume_by_year' => $data['volume_by_year'] ?? [],
            'volume_by_status' => $data['volume_by_status'] ?? [],
            'top_companies' => $data['top_companies'] ?? [],
            'has_suspect_amounts' => $hasSuspect,
            'detail_url' => isset($data['organization_id'])
                ? "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/{$data['organization_id']}"
                : null,
            'public_url' => isset($data['organization_id'])
                ? "https://gobtracker.tailor-bytes.com/organismos/{$data['organization_id']}"
                : null,
        ];
    }
}
