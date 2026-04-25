<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpOrganizationStatsResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

#[Description(
    'Estadísticas agregadas de contratación por organismo: volumen por año, por estado '.
    'y top empresas adjudicatarias. Excluye contratos anulados.'
)]
class OrganizationStatsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — agregados por organismo',
                note: 'id es obligatorio.',
            ));
        }

        $org = Organization::query()->find($id);
        if ($org === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — agregados por organismo',
                note: "Organismo {$id} no encontrado.",
            ));
        }

        $byYear = Contract::query()
            ->where('organization_id', $id)
            ->whereNull('annulled_at')
            ->whereNotNull('snapshot_updated_at')
            ->selectRaw('YEAR(snapshot_updated_at) as year, COUNT(*) as count, COALESCE(SUM(importe_con_iva), 0) as amount')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get()
            ->map(fn ($r) => [
                'year' => (int) $r->year,
                'count' => (int) $r->count,
                'amount' => (float) $r->amount,
            ])
            ->all();

        $byStatus = Contract::query()
            ->where('organization_id', $id)
            ->whereNull('annulled_at')
            ->selectRaw('status_code, COUNT(*) as count, COALESCE(SUM(importe_con_iva), 0) as amount')
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'status' => (string) $r->status_code,
                'count' => (int) $r->count,
                'amount' => (float) $r->amount,
            ])
            ->all();

        $topCompanies = DB::table('awards')
            ->join('contract_lots', 'contract_lots.id', '=', 'awards.contract_lot_id')
            ->join('contracts', 'contracts.id', '=', 'contract_lots.contract_id')
            ->leftJoin('companies', 'companies.id', '=', 'awards.company_id')
            ->where('contracts.organization_id', $id)
            ->whereNull('contracts.annulled_at')
            ->whereNotNull('awards.company_id')
            ->selectRaw(
                'awards.company_id as company_id, '.
                'companies.name as name, '.
                'companies.nif as nif, '.
                'COUNT(awards.id) as awards_count, '.
                'COALESCE(SUM(awards.amount), 0) as total'
            )
            ->groupBy('awards.company_id', 'companies.name', 'companies.nif')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'company_id' => (int) $r->company_id,
                'name' => $r->name,
                'nif' => $r->nif,
                'awards_count' => (int) $r->awards_count,
                'total' => (float) $r->total,
                'suspect_amount' => ((float) $r->total) >= 1_000_000_000.0,
            ])
            ->all();

        $payload = [
            'organization_id' => $id,
            'organization_name' => $org->name,
            'volume_by_year' => $byYear,
            'volume_by_status' => $byStatus,
            'top_companies' => $topCompanies,
        ];

        return Response::json(McpResponseEnvelope::single(
            McpOrganizationStatsResource::make($payload),
            source: 'PLACSP — agregados por organismo',
            note: 'Excluye contratos anulados. Cifras >= 1.000 M € pueden ser erratas del feed.',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('ID interno del organismo.'),
        ];
    }
}
