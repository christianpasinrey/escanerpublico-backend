<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Series temporales agregadas para los hero de cada módulo.
 *
 * Acepta `?module=X` para fetch parcial (más rápido para cada index, payload menor).
 * Sin parámetro devuelve todas las series.
 *
 * Cacheado al edge de CF 1h + Redis 30min — las queries hacen GROUP BY YEAR sobre
 * tablas de millones de filas y son caras.
 */
class TimelinesController extends Controller
{
    private const CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    /**
     * Mapping module → callable que computa la serie.
     * Cada callable usa la fecha más representativa del registro (no created_at,
     * que sería solo cuando lo ingestamos nosotros y daría una sola barra reciente).
     *
     * @var array<string, callable(): array<string, int>>
     */
    private array $modules;

    public function __construct()
    {
        $this->modules = [
            'contracts' => fn () => $this->yearlyCount('contracts', 'fecha_inicio'),
            // Empresas: cuántas distintas recibieron al menos una adjudicación cada año.
            'companies' => fn () => $this->yearlyDistinct('awards', 'company_id', 'award_date'),
            // Organismos: cuántos distintos firmaron al menos un contrato cada año.
            'organizations' => fn () => $this->yearlyDistinct('contracts', 'organization_id', 'fecha_inicio'),
            'subsidies_grants' => fn () => $this->yearlyCount('subsidy_grants', 'grant_date'),
            'subsidies_calls' => fn () => $this->yearlyCount('subsidy_calls', 'reception_date'),
            'legislation_norms' => fn () => $this->yearlyCount('legislation_norms', 'fecha_publicacion'),
            'legislation_items' => fn () => $this->yearlyCount('boe_items', 'fecha_publicacion'),
            'officials' => fn () => $this->yearlyCount('public_officials', 'first_appointment_date'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $module = $request->query('module');

        if (is_string($module) && $module !== '') {
            if (! isset($this->modules[$module])) {
                return response()->json(['error' => 'unknown module'], 400);
            }
            $payload = [$module => $this->cached($module)];
        } else {
            $payload = [];
            foreach ($this->modules as $key => $_) {
                $payload[$key] = $this->cached($key);
            }
        }

        return response()
            ->json($payload)
            ->header('Cache-Control', self::CACHE);
    }

    /**
     * @return array<string, int>
     */
    private function cached(string $module): array
    {
        return Cache::remember("timelines:{$module}", 1800, fn () => ($this->modules[$module])());
    }

    /**
     * @return array<string, int>
     */
    private function yearlyCount(string $table, string $dateColumn): array
    {
        try {
            $rows = DB::table($table)
                ->selectRaw("YEAR({$dateColumn}) as y, COUNT(*) as c")
                ->whereNotNull($dateColumn)
                ->whereYear($dateColumn, '>=', 2010)
                ->whereYear($dateColumn, '<=', (int) date('Y') + 1)
                ->groupBy('y')
                ->orderBy('y')
                ->get();

            return $this->normalize($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Cuenta DISTINCT de una columna agrupada por año de otra fecha.
     * Útil cuando interesa "cuántas entidades únicas estuvieron activas cada año".
     *
     * @return array<string, int>
     */
    private function yearlyDistinct(string $table, string $distinctColumn, string $dateColumn): array
    {
        try {
            $rows = DB::table($table)
                ->selectRaw("YEAR({$dateColumn}) as y, COUNT(DISTINCT {$distinctColumn}) as c")
                ->whereNotNull($distinctColumn)
                ->whereNotNull($dateColumn)
                ->whereYear($dateColumn, '>=', 2010)
                ->whereYear($dateColumn, '<=', (int) date('Y') + 1)
                ->groupBy('y')
                ->orderBy('y')
                ->get();

            return $this->normalize($rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<string, int>
     */
    private function normalize($rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->y] = (int) $r->c;
        }

        return $out;
    }
}
