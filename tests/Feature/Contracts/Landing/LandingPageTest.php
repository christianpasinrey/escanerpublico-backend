<?php

namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_with_stats(): void
    {
        Contract::factory()->count(5)->create();

        $r = $this->get('/');

        $r->assertSuccessful();
        $r->assertSee('Escáner Público');
        $r->assertSee('API abierta');
        $r->assertHeader('Cache-Control');
    }

    public function test_home_shows_curl_snippets(): void
    {
        $r = $this->get('/');

        $r->assertSuccessful();
        $r->assertSee('curl', false);
        $r->assertSee('/api/v1/contracts', false);
    }

    public function test_home_shows_top_organizations_when_present(): void
    {
        Contract::factory()->count(3)->create(['importe_con_iva' => 50000]);

        $r = $this->get('/');

        $r->assertSuccessful();
        $r->assertSee('Top 10 órganos');
    }

    public function test_home_shows_resources_section(): void
    {
        $r = $this->get('/');

        $r->assertSee('/docs', false);
        $r->assertSee('/openapi.json', false);
    }
}
