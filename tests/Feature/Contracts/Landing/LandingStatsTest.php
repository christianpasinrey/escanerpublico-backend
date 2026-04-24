<?php

namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\Stats\LandingStatsService;
use Tests\TestCase;

class LandingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_returns_counters_and_top_organizations(): void
    {
        Contract::factory()->count(5)->create(['importe_con_iva' => 1000]);

        $s = app(LandingStatsService::class)->compute();

        $this->assertEquals(5, $s['total_contracts']);
        $this->assertArrayHasKey('total_amount', $s);
        $this->assertArrayHasKey('total_organizations', $s);
        $this->assertArrayHasKey('total_companies', $s);
        $this->assertArrayHasKey('top_organizations', $s);
        $this->assertArrayHasKey('last_snapshot_at', $s);
        $this->assertArrayHasKey('recent_awarded', $s);
    }

    public function test_refresh_command_caches_stats(): void
    {
        Contract::factory()->count(3)->create();

        Cache::forget('landing:stats');

        $this->artisan('landing:refresh-stats')->assertSuccessful();

        $this->assertNotNull(Cache::get('landing:stats'));
        $this->assertEquals(3, Cache::get('landing:stats')['total_contracts']);
    }

    public function test_cached_returns_data_without_recomputing_when_present(): void
    {
        Cache::put('landing:stats', ['total_contracts' => 999, 'sentinel' => true], 60);

        $svc = app(LandingStatsService::class);
        $result = $svc->cached();

        $this->assertEquals(999, $result['total_contracts']);
        $this->assertTrue($result['sentinel']);
    }
}
