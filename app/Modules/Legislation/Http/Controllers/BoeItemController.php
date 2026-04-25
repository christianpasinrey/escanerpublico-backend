<?php

namespace Modules\Legislation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Pagination\FastPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legislation\Http\Resources\BoeItemResource;
use Modules\Legislation\Models\BoeItem;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BoeItemController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=300, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $q = QueryBuilder::for(BoeItem::class)
            ->allowedFilters(
                'organization_id',
                'seccion_code',
                'departamento_code',
                'summary_id',
                AllowedFilter::callback('search', fn ($q, $v) => $q->whereFullText('titulo', (string) $v)),
                AllowedFilter::callback('fecha_from', fn ($q, $v) => $q->whereDate('fecha_publicacion', '>=', $v)),
                AllowedFilter::callback('fecha_to', fn ($q, $v) => $q->whereDate('fecha_publicacion', '<=', $v)),
            )
            ->allowedIncludes('summary', 'organization')
            ->allowedSorts('fecha_publicacion', 'created_at')
            ->defaultSort('-fecha_publicacion');

        $page = max(1, (int) $request->query('page', '1'));
        $hasFilters = is_array($request->query('filter')) && count($request->query('filter')) > 0;
        $paginated = FastPaginator::paginate($q, $perPage, $page, 'boe_items', $hasFilters)->appends($request->query());

        return BoeItemResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $item): JsonResponse
    {
        $model = QueryBuilder::for(BoeItem::where('id', $item))
            ->allowedIncludes('summary', 'organization')
            ->firstOrFail();

        return BoeItemResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
