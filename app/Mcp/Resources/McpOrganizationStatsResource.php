<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para estadísticas agregadas de un organismo. Recibe un payload
 * con tres colecciones de filas DB (yearly, status, company) y un par de
 * metadatos del organismo. Toda la serialización fila-a-fila vive aquí
 * para no contaminar el handle() del Tool.
 *
 * Estructura esperada:
 * [
 *   'organization_id' => int,
 *   'organization_name' => string,
 *   'volume_by_year' => Collection<stdClass{year, count, amount}>,
 *   'volume_by_status' => Collection<stdClass{status_code, count, amount}>,
 *   'top_companies' => Collection<stdClass{company_id, name, nif, awards_count, total}>,
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

        $byYear = $this->shapeByYear($data['volume_by_year'] ?? []);
        $byStatus = $this->shapeByStatus($data['volume_by_status'] ?? []);
        $topCompanies = $this->shapeTopCompanies($data['top_companies'] ?? []);

        $hasSuspect = false;
        foreach ($byYear as $row) {
            if (($row['amount'] ?? 0) >= 1_000_000_000.0) {
                $hasSuspect = true;
                break;
            }
        }
        if (! $hasSuspect) {
            foreach ($topCompanies as $row) {
                if (($row['total'] ?? 0) >= 1_000_000_000.0) {
                    $hasSuspect = true;
                    break;
                }
            }
        }

        return [
            'organization_id' => $data['organization_id'] ?? null,
            'organization_name' => $data['organization_name'] ?? null,
            'volume_by_year' => $byYear,
            'volume_by_status' => $byStatus,
            'top_companies' => $topCompanies,
            'has_suspect_amounts' => $hasSuspect,
            'detail_url' => isset($data['organization_id'])
                ? "https://app.gobtracker.tailor-bytes.com/api/v1/organizations/{$data['organization_id']}"
                : null,
            'public_url' => isset($data['organization_id'])
                ? "https://gobtracker.tailor-bytes.com/organismos/{$data['organization_id']}"
                : null,
        ];
    }

    /**
     * @param  iterable<int, object|array<string,mixed>>  $rows
     * @return list<array<string, int|float>>
     */
    private function shapeByYear(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $year = is_object($r) ? ($r->year ?? null) : ($r['year'] ?? null);
            $count = is_object($r) ? ($r->count ?? 0) : ($r['count'] ?? 0);
            $amount = is_object($r) ? ($r->amount ?? 0) : ($r['amount'] ?? 0);
            $out[] = [
                'year' => (int) $year,
                'count' => (int) $count,
                'amount' => (float) $amount,
            ];
        }

        return $out;
    }

    /**
     * @param  iterable<int, object|array<string,mixed>>  $rows
     * @return list<array{status:string, count:int, amount:float}>
     */
    private function shapeByStatus(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $status = is_object($r) ? ($r->status_code ?? null) : ($r['status_code'] ?? null);
            $count = is_object($r) ? ($r->count ?? 0) : ($r['count'] ?? 0);
            $amount = is_object($r) ? ($r->amount ?? 0) : ($r['amount'] ?? 0);
            $out[] = [
                'status' => (string) $status,
                'count' => (int) $count,
                'amount' => (float) $amount,
            ];
        }

        return $out;
    }

    /**
     * @param  iterable<int, object|array<string,mixed>>  $rows
     * @return list<array<string, int|float|string|null|bool>>
     */
    private function shapeTopCompanies(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $companyId = is_object($r) ? ($r->company_id ?? null) : ($r['company_id'] ?? null);
            $name = is_object($r) ? ($r->name ?? null) : ($r['name'] ?? null);
            $nif = is_object($r) ? ($r->nif ?? null) : ($r['nif'] ?? null);
            $awardsCount = is_object($r) ? ($r->awards_count ?? 0) : ($r['awards_count'] ?? 0);
            $total = is_object($r) ? ($r->total ?? 0) : ($r['total'] ?? 0);
            $out[] = [
                'company_id' => (int) $companyId,
                'name' => $name,
                'nif' => $nif,
                'awards_count' => (int) $awardsCount,
                'total' => (float) $total,
                'suspect_amount' => ((float) $total) >= 1_000_000_000.0,
            ];
        }

        return $out;
    }
}
