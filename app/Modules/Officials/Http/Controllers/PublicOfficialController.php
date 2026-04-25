<?php

namespace Modules\Officials\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Officials\Http\Resources\PublicOfficialResource;
use Modules\Officials\Models\PublicOfficial;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class PublicOfficialController extends Controller
{
    private const INDEX_CACHE = 'public, s-maxage=60, stale-while-revalidate=300';

    private const SHOW_CACHE = 'public, s-maxage=600, stale-while-revalidate=3600';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));

        $paginated = QueryBuilder::for(PublicOfficial::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn ($q, $v) => $q->whereFullText('full_name', (string) $v)),
                AllowedFilter::callback('name_like', fn ($q, $v) => $q->where('full_name', 'like', '%'.$v.'%')),
            )
            ->allowedIncludes('appointments')
            ->allowedSorts(
                'full_name',
                'appointments_count',
                'last_event_date',
                'first_appointment_date',
                AllowedSort::field('name', 'full_name')
            )
            ->defaultSort('-last_event_date')
            ->paginate($perPage)
            ->appends($request->query());

        return PublicOfficialResource::collection($paginated)
            ->response()
            ->header('Cache-Control', self::INDEX_CACHE);
    }

    public function show(int $official): JsonResponse
    {
        $model = QueryBuilder::for(PublicOfficial::where('id', $official))
            ->allowedIncludes(
                'appointments',
                'appointments.organization',
                'appointments.boeItem'
            )
            ->firstOrFail();

        return PublicOfficialResource::make($model)
            ->response()
            ->header('Cache-Control', self::SHOW_CACHE);
    }
}
