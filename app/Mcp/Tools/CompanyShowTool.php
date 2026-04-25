<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpCompanyDetailResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Company;

#[Description(
    'Ficha completa de una empresa adjudicataria. Incluye agregados (count + total), '.
    'top awards por importe y desglose por organismo (cuánto recibe de cada organismo).'
)]
class CompanyShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — adjudicatarios',
                note: 'id es obligatorio.',
            ));
        }

        $company = Company::query()
            ->withCount('awards')
            ->withSum('awards as awards_sum_amount', 'amount')
            ->with(['awards' => fn ($q) => $q->orderByDesc('amount')->limit(10)])
            ->find($id);

        if ($company === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — adjudicatarios',
                note: "Empresa {$id} no encontrada.",
            ));
        }

        $organisms = DB::table('awards')
            ->join('contract_lots', 'contract_lots.id', '=', 'awards.contract_lot_id')
            ->join('contracts', 'contracts.id', '=', 'contract_lots.contract_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'contracts.organization_id')
            ->where('awards.company_id', $id)
            ->whereNull('contracts.annulled_at')
            ->whereNotNull('contracts.organization_id')
            ->selectRaw(
                'contracts.organization_id as organization_id, '.
                'organizations.name as name, '.
                'organizations.nif as nif, '.
                'COUNT(awards.id) as awards_count, '.
                'COALESCE(SUM(awards.amount), 0) as total'
            )
            ->groupBy('contracts.organization_id', 'organizations.name', 'organizations.nif')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'organization_id' => (int) $r->organization_id,
                'name' => $r->name,
                'nif' => $r->nif,
                'awards_count' => (int) $r->awards_count,
                'total' => (float) $r->total,
                'suspect_amount' => ((float) $r->total) >= 1_000_000_000.0,
            ])
            ->all();

        $company->setAttribute('organisms_breakdown', $organisms);

        return Response::json(McpResponseEnvelope::single(
            McpCompanyDetailResource::make($company),
            source: 'PLACSP — adjudicatarios',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('ID interno de la empresa (ver companies_search).'),
        ];
    }
}
