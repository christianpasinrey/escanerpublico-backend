<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\CompanyResource;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Services\Stats\CompanyStatsService;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=60, stale-while-revalidate=300';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    private const STATS_CACHE = 'public, s-maxage=900, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(Company::class)
            ->allowedFilters('nif', 'name')
            ->allowedIncludes('addresses', 'awards')
            ->allowedSorts('name', 'created_at')
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
