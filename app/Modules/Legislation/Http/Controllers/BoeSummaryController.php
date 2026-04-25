<?php

namespace Modules\Legislation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legislation\Http\Resources\BoeSummaryResource;
use Modules\Legislation\Models\BoeSummary;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BoeSummaryController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=300, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(BoeSummary::class)
            ->withCount('items')
            ->allowedFilters(
                'identificador',
                AllowedFilter::callback('fecha_from', fn ($q, $v) => $q->whereDate('fecha_publicacion', '>=', $v)),
                AllowedFilter::callback('fecha_to', fn ($q, $v) => $q->whereDate('fecha_publicacion', '<=', $v)),
            )
            ->allowedIncludes('items', 'items.organization')
            ->allowedSorts('fecha_publicacion', 'created_at')
            ->defaultSort('-fecha_publicacion')
            ->paginate($perPage)
            ->appends($request->query());

        return BoeSummaryResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $summary): JsonResponse
    {
        $model = QueryBuilder::for(BoeSummary::where('id', $summary))
            ->withCount('items')
            ->allowedIncludes('items', 'items.organization')
            ->firstOrFail();

        return BoeSummaryResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
