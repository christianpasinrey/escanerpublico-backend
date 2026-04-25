<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\CompanyResource;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Services\Stats\CompanyStatsService;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    private const STATS_CACHE = 'public, s-maxage=900, stale-while-revalidate=3600';

    /**
     * Importe a partir del cual una adjudicación se considera dato sospechoso de la fuente PLACSP
     * (datos atípicos publicados con dígitos extra por error humano del organismo). Quedan excluidos
     * de las agregaciones del listado y de los rankings, pero se siguen mostrando en la ficha
     * individual con su bandera para mantener transparencia.
     */
    private const SUSPECT_AMOUNT_THRESHOLD = 1_000_000_000.0;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));
        $threshold = self::SUSPECT_AMOUNT_THRESHOLD;

        $paginated = QueryBuilder::for(Company::class)
            ->withCount(['awards as awards_count' => fn ($q) => $q->where('amount', '<', $threshold)])
            ->withSum(['awards as awards_sum_amount' => fn ($q) => $q->where('amount', '<', $threshold)], 'amount')
            ->allowedFilters('nif', 'name')
            ->allowedIncludes('addresses', 'awards')
            ->allowedSorts(
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('awards_count'),
                AllowedSort::field('total_amount', 'awards_sum_amount'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends($request->query());

        return CompanyResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $company): JsonResponse
    {
        $model = QueryBuilder::for(Company::where('id', $company))
            ->allowedIncludes(
                'addresses',
                'contacts',
                'awards',
                'awards.contractLot',
                'awards.contractLot.contract',
                'awards.contractLot.contract.organization',
            )
            ->firstOrFail();

        return CompanyResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }

    public function stats(int $company, CompanyStatsService $stats): JsonResponse
    {
        $model = Company::findOrFail($company);

        return response()
            ->json($stats->compute($model))
            ->header('Cache-Control', self::STATS_CACHE);
    }
}
