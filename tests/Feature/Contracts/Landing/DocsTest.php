<?php

namespace Tests\Feature\Contracts\Landing;

use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_openapi_json_returns_valid_spec(): void
    {
        $r = $this->getJson('/openapi.json');

        $r->assertSuccessful();
        $r->assertJsonStructure(['openapi', 'info', 'paths']);
        $this->assertMatchesRegularExpression('/^3\./', $r->json('openapi'));
    }

    public function test_openapi_json_advertises_app_title(): void
    {
        $r = $this->getJson('/openapi.json');

        $r->assertJsonPath('info.title', 'Escáner Público — API');
    }

    public function test_docs_page_renders(): void
    {
        $r = $this->get('/docs');

        $r->assertSuccessful();
    }
}
