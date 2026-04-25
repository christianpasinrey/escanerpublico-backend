<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\LotResource;
use Modules\Contracts\Models\ContractLot;
use Spatie\QueryBuilder\QueryBuilder;

class LotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(ContractLot::class)
            ->allowedFilters('contract_id', 'tipo_contrato_code', 'nuts_code')
            ->allowedIncludes('contract', 'awards', 'awards.company', 'criteria')
            ->allowedSorts('lot_number', 'created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return LotResource::collection($paginated)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=900');
    }
}
