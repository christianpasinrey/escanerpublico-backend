<?php

namespace Modules\Tax\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Http\Resources\TaxRegimeResource;
use Modules\Tax\Models\TaxRegime;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class RegimeController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '50')));

        $paginated = QueryBuilder::for(TaxRegime::class)
            ->allowedFilters(
                AllowedFilter::exact('scope'),
                AllowedFilter::exact('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('search', 'name'),
            )
            ->allowedIncludes('obligations', 'compatibleRegimes')
            ->allowedSorts(
                AllowedSort::field('code'),
                AllowedSort::field('scope'),
                AllowedSort::field('name'),
            )
            ->defaultSort('scope', 'code')
            ->paginate($perPage)
            ->appends($request->query());

        return TaxRegimeResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $regime = QueryBuilder::for(TaxRegime::query()->where('code', $code))
            ->allowedIncludes('obligations', 'compatibleRegimes')
            ->firstOrFail();

        return TaxRegimeResource::make($regime)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
