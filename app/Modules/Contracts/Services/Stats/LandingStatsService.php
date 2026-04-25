<?php

namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

class LandingStatsService
{
    public const CACHE_KEY = 'landing:stats';

    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Compute the full landing stats payload from the database.
     *
     * @return array<string, mixed>
     */
    public function compute(): array
    {
        $topOrgsRaw = DB::table('contracts')
            ->selectRaw('organization_id, COUNT(*) as cnt, SUM(importe_con_iva) as total')
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $orgIds = $topOrgsRaw->pluck('organization_id')->all();
        $orgNames = Organization::query()
            ->whereIn('id', $orgIds)
            ->pluck('name', 'id');

        $topOrganizations = $topOrgsRaw->map(fn ($r): array => [
            'id' => (int) $r->organization_id,
            'name' => $orgNames->get($r->organization_id),
            'contracts' => (int) $r->cnt,
            'total' => (float) $r->total,
        ])->all();

        $recentAwarded = Contract::query()
            ->where('status_code', 'ADJ')
            ->orderByDesc('snapshot_updated_at')
            ->limit(10)
            ->get(['id', 'external_id', 'expediente', 'objeto', 'importe_con_iva', 'snapshot_updated_at'])
            ->toArray();

        return [
            'total_contracts' => Contract::query()->count(),
            'total_organizations' => Organization::query()->count(),
            'total_companies' => Company::query()->count(),
            'total_amount' => (float) Contract::query()->sum('importe_con_iva'),
            'last_snapshot_at' => Contract::query()->max('snapshot_updated_at'),
            'top_organizations' => $topOrganizations,
            'recent_awarded' => $recentAwarded,
        ];
    }

    /**
     * Return cached stats, recomputing if missing.
     *
     * @return array<string, mixed>
     */
    public function cached(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        return $this->refresh();
    }

    /**
     * Recompute stats and store them in cache.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $data = $this->compute();
        Cache::put(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }
}
