<?php

namespace Modules\Tax\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Services\ObligationsResolver;

class ObligationCalendarController extends Controller
{
    private const CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    public function __construct(private readonly ObligationsResolver $resolver) {}

    public function show(Request $request): JsonResponse
    {
        $regimeCode = $request->query('regime');
        $year = (int) ($request->query('year', date('Y')));

        if (! is_string($regimeCode) || $regimeCode === '') {
            return response()->json([
                'error' => 'Parámetro requerido: ?regime={code}',
                'code' => 'REGIME_REQUIRED',
            ], 422);
        }

        if ($year < 2000 || $year > 2100) {
            return response()->json([
                'error' => 'Año fuera de rango (2000-2100).',
                'code' => 'YEAR_OUT_OF_RANGE',
            ], 422);
        }

        $regime = TaxRegime::query()->where('code', $regimeCode)->first();

        if ($regime === null) {
            return response()->json([
                'error' => "No existe el régimen con código {$regimeCode}.",
                'code' => 'REGIME_NOT_FOUND',
            ], 404);
        }

        $calendar = $this->resolver->calendarFor($regime, $year);

        return response()->json(['data' => $calendar])
            ->header('Cache-Control', self::CACHE);
    }
}
