<?php

namespace Tests\Benchmarks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class ApiEndpointBenchmark extends TestCase
{
    use RefreshDatabase;

    public function test_contract_index_under_300ms_warm_cache(): void
    {
        Contract::factory()->count(100)->create();

        // Warm the caches (route, config, opcache, query planner).
        $this->getJson('/api/v1/contracts?per_page=25');

        $start = hrtime(true);
        $this->getJson('/api/v1/contracts?per_page=25');
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        // Emit measurement for benchmarks dashboard / CI parsing.
        fwrite(STDERR, sprintf("\n[BENCHMARK] contract_index_warm=%.2fms\n", $durationMs));

        $this->assertLessThan(300, $durationMs, "Warm cache took {$durationMs}ms");
    }

    public function test_contract_show_under_700ms_cold_cache(): void
    {
        $c = Contract::factory()->create();
        Cache::flush();

        $start = hrtime(true);
        $this->getJson('/api/v1/contracts/'.urlencode($c->external_id).'?include=lots,notices,documents');
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        fwrite(STDERR, sprintf("\n[BENCHMARK] contract_show_cold=%.2fms\n", $durationMs));

        $this->assertLessThan(700, $durationMs, "Cold cache took {$durationMs}ms");
    }
}
