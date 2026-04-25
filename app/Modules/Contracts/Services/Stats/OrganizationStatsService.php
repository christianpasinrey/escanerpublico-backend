<?php

namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

class OrganizationStatsService
{
    /**
     * Importe a partir del cual una adjudicación se considera dato atípico de PLACSP
     * (errores humanos en publicación). Excluido del ranking top_companies.
     */
    public const SUSPECT_AMOUNT_THRESHOLD = 1_000_000_000.0;

    /**
     * @return array<string, mixed>
     */
    public function compute(Organization $org): array
    {
        $base = Contract::where('organization_id', $org->id);

        $totalContracts = (clone $base)->count();
        $totalAmount = (float) (clone $base)->sum('importe_con_iva');

        $byStatus = (clone $base)
            ->selectRaw('status_code, COUNT(*) as cnt')
            ->groupBy('status_code')
            ->pluck('cnt', 'status_code')
            ->toArray();

        $byType = (clone $base)
            ->whereNotNull('tipo_contrato_code')
            ->selectRaw('tipo_contrato_code, COUNT(*) as cnt')
            ->groupBy('tipo_contrato_code')
            ->pluck('cnt', 'tipo_contrato_code')
            ->toArray();

        $byYear = (clone $base)
            ->selectRaw('YEAR(fecha_inicio) as y, SUM(importe_con_iva) as total')
            ->whereNotNull('fecha_inicio')
            ->groupBy('y')
            ->orderBy('y')
            ->pluck('total', 'y')
            ->toArray();

        $contractIds = (clone $base)->pluck('id');

        $uniqueCompanies = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->whereIn('contract_lots.contract_id', $contractIds)
            ->distinct('awards.company_id')
            ->count('awards.company_id');

        $topCompanies = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->join('companies', 'awards.company_id', '=', 'companies.id')
            ->whereIn('contract_lots.contract_id', $contractIds)
            ->whereNotNull('awards.company_id')
            ->where('awards.amount', '<', self::SUSPECT_AMOUNT_THRESHOLD)
            ->selectRaw('companies.id, companies.name, companies.nif, COUNT(DISTINCT contract_lots.contract_id) as contracts_count, SUM(awards.amount) as total_amount')
            ->groupBy('companies.id', 'companies.name', 'companies.nif')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'nif' => $r->nif,
                'contracts_count' => (int) $r->contracts_count,
                'total_amount' => (float) $r->total_amount,
            ])
            ->toArray();

        return [
            'total_contracts' => $totalContracts,
            'total_amount' => $totalAmount,
            'avg_amount' => $totalContracts > 0 ? round($totalAmount / $totalContracts, 2) : 0,
            'unique_companies' => $uniqueCompanies,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'by_year' => $byYear,
            'top_companies' => $topCompanies,
        ];
    }
}
