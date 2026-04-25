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
     * Si el código existe en varias filas (típico para tributos cedidos:
     * versión estatal + versiones autonómicas), se devuelve la fila más
     * representativa siguiendo este orden de preferencia:
     *
     *   1. Si hay filtros explícitos (region_code/scope), aplicarlos.
     *   2. Si existe una fila estatal (scope=state), devolverla.
     *   3. Si solo hay regionales, devolver la primera ordenada por region_code.
     *
     * En cualquier caso, si hay versiones adicionales el resource expone
     * `regional_variants` con la lista de las restantes para que el cliente
     * pueda navegar entre ellas.
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

        // Preferir fila estatal cuando hay varias y no se ha filtrado por region/scope.
        $primary = $matches->first(fn (TaxType $t) => $t->region_code === null) ?? $matches->first();

        // Listado de variantes regionales (todas las filas excepto la principal).
        $variants = $matches
            ->reject(fn (TaxType $t) => $t->id === $primary->id)
            ->values()
            ->map(fn (TaxType $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'scope' => $t->scope instanceof \BackedEnum ? $t->scope->value : (string) $t->scope,
                'region_code' => $t->region_code,
                'name' => $t->name,
                'rates_count' => $t->rates_count ?? 0,
            ])
            ->all();

        return TaxTypeResource::make($primary)
            ->additional(['meta' => ['regional_variants' => $variants]])
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
