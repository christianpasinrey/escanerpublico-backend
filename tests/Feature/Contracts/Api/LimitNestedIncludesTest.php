<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitNestedIncludesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_include_with_more_than_4_nesting_levels(): void
    {
        $resp = $this->getJson('/api/v1/contracts?include=a.b.c.d.e');
        $resp->assertStatus(400);
        $resp->assertJsonPath('error', 'include_too_deep');
    }

    public function test_allows_3_levels(): void
    {
        $resp = $this->getJson('/api/v1/contracts?include=lots.awards.company');
        $resp->assertSuccessful();
    }
}
