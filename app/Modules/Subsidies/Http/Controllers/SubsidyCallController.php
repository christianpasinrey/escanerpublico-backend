<?php

namespace Modules\Subsidies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subsidies\Http\Resources\SubsidyCallResource;
use Modules\Subsidies\Models\SubsidyCall;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class SubsidyCallController extends Controller
{
    // TTL bajos durante fase de ingesta inicial — subimos cuando el dataset esté completo.
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=300, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(SubsidyCall::class)
            ->withCount('grants')
            ->withSum('grants', 'amount')
            ->allowedFilters(
                'organization_id',
                'numero_convocatoria',
                'nivel1',
                'is_mrr',
                AllowedFilter::callback('search', fn ($q, $v) => $q->whereFullText('description', (string) $v))
            )
            ->allowedIncludes('organization', 'grants')
            ->allowedSorts(
                'reception_date',
                'created_at',
                'updated_at',
                AllowedSort::field('grants_count'),
                AllowedSort::field('total_amount', 'grants_sum_amount'),
            )
            ->defaultSort('-reception_date')
            ->paginate($perPage)
            ->appends($request->query());

        return SubsidyCallResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $call): JsonResponse
    {
        $model = QueryBuilder::for(SubsidyCall::where('id', $call))
            ->withCount('grants')
            ->withSum('grants', 'amount')
            ->allowedIncludes('organization', 'grants', 'grants.company')
            ->firstOrFail();

        return SubsidyCallResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
