<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\Stats\OrganizationStatsService;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    private const STATS_CACHE = 'public, s-maxage=900, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(Organization::class)
            ->withCount('contracts')
            ->withSum('contracts', 'importe_con_iva')
            ->allowedFilters('identifier', 'nif', 'type_code', 'activity_code')
            ->allowedIncludes('addresses', 'contacts', 'contracts')
            ->allowedSorts(
                'name',
                'created_at',
                'updated_at',
                AllowedSort::field('contracts_count'),
                AllowedSort::field('total_amount', 'contracts_sum_importe_con_iva'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends($request->query());

        return OrganizationResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $organization): JsonResponse
    {
        $org = QueryBuilder::for(Organization::where('id', $organization))
            ->allowedIncludes('addresses', 'contacts', 'contracts')
            ->firstOrFail();

        return OrganizationResource::make($org)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }

    public function stats(int $organization, OrganizationStatsService $stats): JsonResponse
    {
        $org = Organization::findOrFail($organization);

        return response()
            ->json($stats->compute($org))
            ->header('Cache-Control', self::STATS_CACHE);
    }
}
