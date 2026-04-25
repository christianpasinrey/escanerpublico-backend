<?php

namespace Modules\Tax\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Http\Resources\AutonomoBracketResource;
use Modules\Tax\Http\Resources\SocialSecurityRateResource;
use Modules\Tax\Http\Resources\TaxBracketResource;
use Modules\Tax\Http\Resources\TaxParameterResource;
use Modules\Tax\Http\Resources\VatProductRateResource;
use Modules\Tax\Models\AutonomoBracket;
use Modules\Tax\Models\SocialSecurityRate;
use Modules\Tax\Models\TaxBracket;
use Modules\Tax\Models\TaxParameter;
use Modules\Tax\Models\VatProductRate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Endpoints públicos de los parámetros numéricos M3 (open data CC-BY).
 *
 * Permite consumir como dataset las escalas IRPF, los tipos SS, los tramos
 * de cotización de autónomos, los tipos IVA por sector y los parámetros
 * sueltos (mínimos personales, retenciones).
 */
class ParametersController extends Controller
{
    private const CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    public function parameters(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginated = QueryBuilder::for(TaxParameter::class)
            ->allowedFilters(
                AllowedFilter::exact('year'),
                AllowedFilter::exact('region_code'),
                AllowedFilter::exact('key'),
                AllowedFilter::partial('search', 'key'),
            )
            ->allowedSorts(
                AllowedSort::field('year'),
                AllowedSort::field('region_code'),
                AllowedSort::field('key'),
            )
            ->defaultSort('-year', 'region_code', 'key')
            ->paginate($perPage)
            ->appends($request->query());

        return TaxParameterResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::CACHE);
    }

    public function brackets(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginated = QueryBuilder::for(TaxBracket::class)
            ->allowedFilters(
                AllowedFilter::exact('year'),
                AllowedFilter::exact('scope'),
                AllowedFilter::exact('region_code'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('year'),
                AllowedSort::field('type'),
                AllowedSort::field('from_amount'),
            )
            ->defaultSort('-year', 'scope', 'type', 'from_amount')
            ->paginate($perPage)
            ->appends($request->query());

        return TaxBracketResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::CACHE);
    }

    public function socialSecurityRates(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginated = QueryBuilder::for(SocialSecurityRate::class)
            ->allowedFilters(
                AllowedFilter::exact('year'),
                AllowedFilter::exact('regime'),
                AllowedFilter::exact('contingency'),
            )
            ->allowedSorts(
                AllowedSort::field('year'),
                AllowedSort::field('regime'),
                AllowedSort::field('contingency'),
            )
            ->defaultSort('-year', 'regime', 'contingency')
            ->paginate($perPage)
            ->appends($request->query());

        return SocialSecurityRateResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::CACHE);
    }

    public function autonomoBrackets(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginated = QueryBuilder::for(AutonomoBracket::class)
            ->allowedFilters(
                AllowedFilter::exact('year'),
                AllowedFilter::exact('bracket_number'),
            )
            ->allowedSorts(
                AllowedSort::field('year'),
                AllowedSort::field('bracket_number'),
            )
            ->defaultSort('-year', 'bracket_number')
            ->paginate($perPage)
            ->appends($request->query());

        return AutonomoBracketResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::CACHE);
    }

    public function vatProductRates(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);

        $paginated = QueryBuilder::for(VatProductRate::class)
            ->allowedFilters(
                AllowedFilter::exact('year'),
                AllowedFilter::exact('rate_type'),
                AllowedFilter::exact('activity_code'),
                AllowedFilter::partial('keyword'),
                AllowedFilter::partial('search', 'keyword'),
            )
            ->allowedSorts(
                AllowedSort::field('year'),
                AllowedSort::field('rate_type'),
                AllowedSort::field('rate'),
            )
            ->defaultSort('-year', 'rate_type')
            ->paginate($perPage)
            ->appends($request->query());

        return VatProductRateResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::CACHE);
    }

    private function resolvePerPage(Request $request): int
    {
        return min(200, max(1, (int) $request->query('per_page', '50')));
    }
}
