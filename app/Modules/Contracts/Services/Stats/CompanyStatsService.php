<?php

namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;

class CompanyStatsService
{
    /**
     * @return array<string, mixed>
     */
    public function compute(Company $c): array
    {
        $base = Award::where('company_id', $c->id);

        $totalAwards = (clone $base)->count();
        $totalAmount = (float) (clone $base)->sum('amount');

        $byYear = (clone $base)
            ->selectRaw('YEAR(award_date) as y, SUM(amount) as total')
            ->whereNotNull('award_date')
            ->groupBy('y')
            ->orderBy('y')
            ->pluck('total', 'y')
            ->toArray();

        $topOrgs = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->join('contracts', 'contract_lots.contract_id', '=', 'contracts.id')
            ->join('organizations', 'contracts.organization_id', '=', 'organizations.id')
            ->where('awards.company_id', $c->id)
            ->selectRaw('organizations.id, organizations.name, organizations.identifier as dir3_code, COUNT(DISTINCT contracts.id) as contracts_count, SUM(awards.amount) as total_amount')
            ->groupBy('organizations.id', 'organizations.name', 'organizations.identifier')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'dir3_code' => $r->dir3_code,
                'contracts_count' => (int) $r->contracts_count,
                'total_amount' => (float) $r->total_amount,
            ])
            ->toArray();

        $byStatus = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->join('contracts', 'contract_lots.contract_id', '=', 'contracts.id')
            ->where('awards.company_id', $c->id)
            ->selectRaw('contracts.status_code, COUNT(DISTINCT contracts.id) as cnt')
            ->groupBy('contracts.status_code')
            ->pluck('cnt', 'status_code')
            ->toArray();

        $uniqueOrgs = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->join('contracts', 'contract_lots.contract_id', '=', 'contracts.id')
            ->where('awards.company_id', $c->id)
            ->distinct('contracts.organization_id')
            ->count('contracts.organization_id');

        $uniqueContracts = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->where('awards.company_id', $c->id)
            ->distinct('contract_lots.contract_id')
            ->count('contract_lots.contract_id');

        return [
            'total_awards' => $totalAwards,
            'total_amount' => $totalAmount,
            'avg_amount' => $totalAwards > 0 ? round($totalAmount / $totalAwards, 2) : 0,
            'unique_organizations' => $uniqueOrgs,
            'unique_contracts' => $uniqueContracts,
            'by_year' => $byYear,
            'by_status' => $byStatus,
            'top_organizations' => $topOrgs,
        ];
    }
}
