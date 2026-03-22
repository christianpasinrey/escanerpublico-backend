<?php

namespace Modules\Contracts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

class OrganizationController
{
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query();

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('nif', 'like', "%{$q}%")
                  ->orWhere('identifier', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->withCount('contracts')
                ->withSum('contracts', 'importe_con_iva')
                ->orderByDesc('contracts_count')
                ->paginate($request->input('per_page', 25))
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load(['addresses.country', 'addresses.city', 'contacts']);
        $organization->loadCount('contracts');

        return response()->json($organization);
    }

    public function stats(Organization $organization): JsonResponse
    {
        $orgId = $organization->id;

        $totalContracts = Contract::where('organization_id', $orgId)->count();
        $totalAmount = (float) Contract::where('organization_id', $orgId)->sum('importe_con_iva');
        $avgAmount = $totalContracts > 0 ? round($totalAmount / $totalContracts, 2) : 0;

        $uniqueCompanies = Award::whereHas('contract', fn($q) => $q->where('organization_id', $orgId))
            ->distinct('company_id')
            ->count('company_id');

        $byStatus = Contract::where('organization_id', $orgId)
            ->selectRaw('status_code, count(*) as total')
            ->groupBy('status_code')
            ->pluck('total', 'status_code');

        $byType = Contract::where('organization_id', $orgId)
            ->whereNotNull('tipo_contrato_code')
            ->selectRaw('tipo_contrato_code, count(*) as total')
            ->groupBy('tipo_contrato_code')
            ->pluck('total', 'tipo_contrato_code');

        $byYear = Contract::where('organization_id', $orgId)
            ->selectRaw('YEAR(created_at) as year, SUM(importe_con_iva) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('total', 'year')
            ->map(fn($v) => round((float) $v, 2));

        $topCompanies = DB::table('awards')
            ->join('contracts', 'awards.contract_id', '=', 'contracts.id')
            ->join('companies', 'awards.company_id', '=', 'companies.id')
            ->where('contracts.organization_id', $orgId)
            ->select(
                'companies.id',
                'companies.name',
                'companies.nif',
                DB::raw('COUNT(*) as contracts_count'),
                DB::raw('SUM(awards.amount) as total_amount')
            )
            ->groupBy('companies.id', 'companies.name', 'companies.nif')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $recentContracts = Contract::where('organization_id', $orgId)
            ->select('id', 'objeto', 'status_code', 'importe_con_iva', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'total_contracts' => $totalContracts,
            'total_amount' => $totalAmount,
            'avg_amount' => $avgAmount,
            'unique_companies' => $uniqueCompanies,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'by_year' => $byYear,
            'top_companies' => $topCompanies,
            'recent_contracts' => $recentContracts,
        ]);
    }
}
