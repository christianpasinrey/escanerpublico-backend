<?php

namespace Modules\Legislation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legislation\Http\Resources\LegislationNormResource;
use Modules\Legislation\Models\LegislationNorm;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LegislationNormController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=300, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(LegislationNorm::class)
            ->allowedFilters(
                'organization_id',
                'ambito_code',
                'rango_code',
                'departamento_code',
                'vigencia_agotada',
                AllowedFilter::callback('search', fn ($q, $v) => $q->whereFullText('titulo', (string) $v)),
                AllowedFilter::callback('fecha_pub_from', fn ($q, $v) => $q->whereDate('fecha_publicacion', '>=', $v)),
                AllowedFilter::callback('fecha_pub_to', fn ($q, $v) => $q->whereDate('fecha_publicacion', '<=', $v)),
            )
            ->allowedIncludes('organization')
            ->allowedSorts('fecha_publicacion', 'fecha_disposicion', 'fecha_vigencia', 'created_at', 'updated_at')
            ->defaultSort('-fecha_publicacion')
            ->paginate($perPage)
            ->appends($request->query());

        return LegislationNormResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $norm): JsonResponse
    {
        $model = QueryBuilder::for(LegislationNorm::where('id', $norm))
            ->allowedIncludes('organization')
            ->firstOrFail();

        return LegislationNormResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
