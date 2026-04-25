<?php

namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TimelinesController extends Controller
{
    /**
     * Series temporales agregadas para los hero de cada módulo.
     * Cacheado al edge de Cloudflare 1h + Redis 5min para minimizar carga sobre BD.
     */
    private const CACHE = 'public, s-maxage=3600, stale-while-revalidate=86400';

    public function index(): JsonResponse
    {
        $payload = [
            'contracts' => $this->yearlyCount('contracts', 'fecha_inicio'),
            'organizations' => $this->yearlyCount('organizations', 'created_at'),
            'companies' => $this->yearlyCount('companies', 'created_at'),
            'subsidies_grants' => $this->yearlyCount('subsidy_grants', 'grant_date'),
            'subsidies_calls' => $this->yearlyCount('subsidy_calls', 'reception_date'),
            'legislation_norms' => $this->yearlyCount('legislation_norms', 'fecha_publicacion'),
            'legislation_items' => $this->yearlyCount('boe_items', 'fecha_publicacion'),
            'officials' => $this->yearlyCount('public_officials', 'first_appointment_date'),
        ];

        return response()
            ->json($payload)
            ->header('Cache-Control', self::CACHE);
    }

    /**
     * @return array<string, int>  ["2018" => 12345, "2019" => 18230, ...]
     */
    private function yearlyCount(string $table, string $dateColumn): array
    {
        // Si la tabla no existe (módulo no migrado), devolvemos array vacío en lugar
        // de fallar.
        try {
            $rows = DB::table($table)
                ->selectRaw("YEAR({$dateColumn}) as y, COUNT(*) as c")
                ->whereNotNull($dateColumn)
                ->whereYear($dateColumn, '>=', 2010)
                ->whereYear($dateColumn, '<=', (int) date('Y') + 1)
                ->groupBy('y')
                ->orderBy('y')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->y] = (int) $r->c;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
