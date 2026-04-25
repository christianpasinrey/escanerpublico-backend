<?php

namespace Modules\Tax\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Http\Resources\TaxTypeResource;
use Modules\Tax\Models\TaxType;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catálogo público de impuestos y tasas (M2).
 *
 * Endpoint open data CC-BY: el catálogo es información de interés público
 * estructurada con cita a BOE consolidado en cada figura.
 */
class TaxTypeController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=300, stale-while-revalidate=900';

    private const SHOW_CACHE = 'public, s-maxage=600, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(TaxType::class)
            ->withCount('rates')
            ->allowedFilters(
                'scope',
                'levy_type',
                'region_code',
                'code',
                AllowedFilter::callback('search', function ($query, $value) {
                    /** @var Builder<TaxType> $query */
                    $query->where(function ($q) use ($value) {
                        $term = '%'.((string) $value).'%';
                        $q->where('code', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
                }),
            )
            ->allowedIncludes('rates')
            ->allowedSorts(
                'code',
                'name',
                'scope',
                'levy_type',
                'region_code',
                AllowedSort::field('rates_count'),
            )
            ->defaultSort('scope', 'levy_type', 'code', 'region_code')
            ->paginate($perPage)
            ->appends($request->query());

        return TaxTypeResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    /**
     * Muestra el detalle de un tax_type por su `code`.
     *
     * Si existen varias filas con el mismo code (caso típico: tributos cedidos
     * con scope=regional para distintas CCAA + uno estatal con scope=state),
     * el cliente debe desambiguar con `?region_code=MD` o `?scope=regional`.
     * Si la consulta es ambigua se responde 422.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $query = QueryBuilder::for(TaxType::query())
            ->withCount('rates')
            ->where('code', $code);

        if ($request->has('region_code')) {
            $regionCode = $request->query('region_code');
            $query->where(function ($q) use ($regionCode) {
                if ($regionCode === '' || $regionCode === null) {
                    $q->whereNull('region_code');
                } else {
                    $q->where('region_code', $regionCode);
                }
            });
        }

        if ($request->has('scope')) {
            $query->where('scope', $request->query('scope'));
        }

        $query->allowedIncludes('rates');

        $matches = $query->get();

        if ($matches->isEmpty()) {
            throw new NotFoundHttpException("No se encontró tax_type con code={$code}");
        }

        if ($matches->count() > 1) {
            return response()->json([
                'message' => 'Código ambiguo: existen varias filas para este code. Especifique scope y/o region_code.',
                'matches' => $matches->map(fn (TaxType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'scope' => $t->scope?->value,
                    'region_code' => $t->region_code,
                    'name' => $t->name,
                ])->all(),
            ], 422);
        }

        return TaxTypeResource::make($matches->first())
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
