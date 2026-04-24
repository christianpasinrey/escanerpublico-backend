<?php

namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\Cache;
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
        $topOrgs = Contract::query()
            ->selectRaw('organization_id, COUNT(*) as cnt, SUM(importe_con_iva) as total')
            ->whereNotNull('organization_id')
            ->with('organization:id,name')
            ->groupBy('organization_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->organization_id,
                'name' => $r->organization?->name,
                'contracts' => (int) $r->cnt,
                'total' => (float) $r->total,
            ])
            ->toArray();

        $recentAwarded = Contract::query()
            ->where('status_code', 'ADJ')
            ->orderByDesc('snapshot_updated_at')
            ->limit(10)
            ->get(['id', 'external_id', 'expediente', 'objeto', 'importe_con_iva', 'snapshot_updated_at'])
            ->toArray();

        return [
            'total_contracts' => Contract::count(),
            'total_organizations' => Organization::count(),
            'total_companies' => Company::count(),
            'total_amount' => (float) Contract::sum('importe_con_iva'),
            'last_snapshot_at' => Contract::max('snapshot_updated_at'),
            'top_organizations' => $topOrgs,
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

        return $cached ?? $this->refresh();
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
