<?php

namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_status_and_counters(): void
    {
        Contract::factory()->count(2)->create();

        $r = $this->getJson('/health');

        $r->assertSuccessful();
        $r->assertJsonStructure(['status', 'snapshot_updated_at', 'contracts']);
        $r->assertJsonPath('status', 'ok');
        $r->assertJsonPath('contracts', 2);
    }

    public function test_health_sets_no_store_cache_header(): void
    {
        $r = $this->getJson('/health');

        $r->assertSuccessful();
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }
}
