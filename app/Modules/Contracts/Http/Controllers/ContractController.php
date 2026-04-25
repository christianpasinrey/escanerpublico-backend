<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Filters\AmountBetweenFilter;
use Modules\Contracts\Http\Filters\SearchFilter;
use Modules\Contracts\Http\Resources\ContractResource;
use Modules\Contracts\Http\Sorts\RelevanceSort;
use Modules\Contracts\Models\Contract;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=60, stale-while-revalidate=300';

    private const SHOW_CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    /**
     * @return array<int, string>
     */
    private function allowedIncludes(): array
    {
        return [
            'organization', 'organization.addresses', 'organization.contacts',
            'lots', 'lots.awards', 'lots.awards.company',
            'lots.criteria',
            'notices', 'modifications', 'documents', 'snapshots',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $q = QueryBuilder::for(Contract::class)
            ->allowedFilters(
                AllowedFilter::exact('status_code'),
                AllowedFilter::exact('tipo_contrato_code'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('nuts_code'),
                AllowedFilter::exact('funding_program_code'),
                AllowedFilter::exact('over_threshold_indicator'),
                AllowedFilter::custom('search', new SearchFilter),
                AllowedFilter::custom('amount_between', new AmountBetweenFilter),
            )
            ->allowedIncludes(...$this->allowedIncludes())
            ->allowedFields(
                'id', 'external_id', 'expediente', 'objeto', 'status_code', 'tipo_contrato_code',
                'importe_con_iva', 'importe_sin_iva', 'valor_estimado', 'fecha_inicio', 'fecha_fin',
                'snapshot_updated_at', 'annulled_at', 'organization_id',
            )
            ->allowedSorts(
                'snapshot_updated_at',
                'importe_con_iva',
                'fecha_inicio',
                AllowedSort::custom('relevance', new RelevanceSort),
            )
            ->defaultSort('-snapshot_updated_at');

        $paginated = $q->paginate($perPage)->appends($request->query());

        return ContractResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(string $externalId): JsonResponse
    {
        // Routing by external_id (URL-encoded or numeric PLACSP id suffix).
        $decoded = urldecode($externalId);
        $query = Contract::query();
        if (str_starts_with($decoded, 'http')) {
            $query->where('external_id', $decoded);
        } else {
            $query->where('external_id', 'LIKE', '%/'.$decoded);
        }

        $contract = QueryBuilder::for($query)
            ->allowedIncludes(...$this->allowedIncludes())
            ->firstOrFail();

        return ContractResource::make($contract)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
