<?php

namespace Modules\Subsidies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subsidies\Http\Resources\SubsidyGrantResource;
use Modules\Subsidies\Models\SubsidyGrant;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SubsidyGrantController extends Controller
{
    // TTL bajos durante fase de ingesta inicial — subimos cuando el dataset esté completo.
    private const INDEX_CACHE = 'public, s-maxage=15, stale-while-revalidate=60';

    private const SHOW_CACHE = 'public, s-maxage=300, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(SubsidyGrant::class)
            ->allowedFilters(
                'organization_id',
                'company_id',
                'call_id',
                'beneficiario_nif',
                'nivel1',
                AllowedFilter::exact('grant_date'),
                AllowedFilter::callback('grant_date_from', fn ($q, $v) => $q->whereDate('grant_date', '>=', $v)),
                AllowedFilter::callback('grant_date_to', fn ($q, $v) => $q->whereDate('grant_date', '<=', $v)),
                AllowedFilter::callback('amount_above', fn ($q, $v) => $q->where('amount', '>=', (float) $v)),
                AllowedFilter::callback('amount_below', fn ($q, $v) => $q->where('amount', '<=', (float) $v)),
                AllowedFilter::callback('beneficiario_search', fn ($q, $v) => $q->where('beneficiario_name', 'like', '%'.$v.'%'))
            )
            ->allowedIncludes('call', 'company', 'organization')
            ->allowedSorts('grant_date', 'amount', 'fecha_alta', 'created_at', 'updated_at')
            ->defaultSort('-grant_date')
            ->paginate($perPage)
            ->appends($request->query());

        return SubsidyGrantResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $grant): JsonResponse
    {
        $model = QueryBuilder::for(SubsidyGrant::where('id', $grant))
            ->allowedIncludes('call', 'company', 'organization')
            ->firstOrFail();

        return SubsidyGrantResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
