<?php

namespace Modules\Tax\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Http\Resources\EconomicActivityResource;
use Modules\Tax\Models\EconomicActivity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class EconomicActivityController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', '50')));

        $paginated = QueryBuilder::for(EconomicActivity::class)
            ->allowedFilters(
                AllowedFilter::exact('system'),
                AllowedFilter::exact('code'),
                AllowedFilter::exact('parent_code'),
                AllowedFilter::exact('level'),
                AllowedFilter::exact('section'),
                AllowedFilter::exact('year'),
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::partial('name'),
            )
            ->allowedIncludes('regimeMapping')
            ->allowedSorts(
                AllowedSort::field('code'),
                AllowedSort::field('level'),
                AllowedSort::field('name'),
            )
            ->defaultSort('system', 'code')
            ->paginate($perPage)
            ->appends($request->query());

        return EconomicActivityResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(Request $request, string $system, string $code): JsonResponse
    {
        if (! in_array($system, ['cnae', 'iae'], true)) {
            abort(404, 'Sistema desconocido. Usa cnae o iae.');
        }

        $activity = QueryBuilder::for(
            EconomicActivity::query()
                ->where('system', $system)
                ->where('code', $code)
                ->orderByDesc('year'),
        )
            ->allowedIncludes('regimeMapping')
            ->firstOrFail();

        return EconomicActivityResource::make($activity)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
