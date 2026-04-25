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
            ->where('awards.company_id', $c->id)
            ->selectRaw('contracts.organization_id, COUNT(*) as cnt, SUM(awards.amount) as total')
            ->groupBy('contracts.organization_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total_awards' => $totalAwards,
            'total_amount' => $totalAmount,
            'by_year' => $byYear,
            'top_organizations' => $topOrgs,
        ];
    }
}
