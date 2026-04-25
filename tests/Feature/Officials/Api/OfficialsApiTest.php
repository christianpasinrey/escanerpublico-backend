<?php

namespace Tests\Feature\Officials\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Models\PublicOfficial;
use Tests\TestCase;

class OfficialsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated(): void
    {
        PublicOfficial::factory()->count(3)->create();
        $r = $this->getJson('/api/v1/officials');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_show_with_appointments(): void
    {
        $official = PublicOfficial::factory()->create();
        Appointment::factory()->for($official, 'publicOfficial')->count(3)->create();

        $r = $this->getJson("/api/v1/officials/{$official->id}?include=appointments");
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data.appointments');
    }

    public function test_show_with_appointments_organization_and_boe_item(): void
    {
        $official = PublicOfficial::factory()->create();
        $org = Organization::factory()->create();
        $boeItem = BoeItem::factory()->create();
        Appointment::factory()
            ->for($official, 'publicOfficial')
            ->for($org, 'organization')
            ->for($boeItem, 'boeItem')
            ->create();

        $r = $this->getJson("/api/v1/officials/{$official->id}?include=appointments.organization,appointments.boeItem");
        $r->assertSuccessful();
        $r->assertJsonPath('data.appointments.0.organization.id', $org->id);
        $r->assertJsonPath('data.appointments.0.boe_item.id', $boeItem->id);
    }

    public function test_filter_by_name_like(): void
    {
        PublicOfficial::factory()->create(['full_name' => 'Juan Pérez García', 'normalized_name' => 'juan perez garcia']);
        PublicOfficial::factory()->create(['full_name' => 'María López', 'normalized_name' => 'maria lopez']);

        $r = $this->getJson('/api/v1/officials?filter[name_like]=Pérez');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data');
        $r->assertJsonPath('data.0.full_name', 'Juan Pérez García');
    }

    public function test_sort_by_appointments_count_desc(): void
    {
        PublicOfficial::factory()->create(['appointments_count' => 1, 'normalized_name' => 'a']);
        PublicOfficial::factory()->create(['appointments_count' => 5, 'normalized_name' => 'b']);
        PublicOfficial::factory()->create(['appointments_count' => 3, 'normalized_name' => 'c']);

        $r = $this->getJson('/api/v1/officials?sort=-appointments_count');
        $r->assertSuccessful();
        $this->assertSame(5, $r->json('data.0.appointments_count'));
        $this->assertSame(3, $r->json('data.1.appointments_count'));
    }

    public function test_disallowed_filter_400(): void
    {
        PublicOfficial::factory()->create();
        $r = $this->getJson('/api/v1/officials?filter[hacker]=evil');
        $r->assertStatus(400);
    }

    public function test_response_has_cache_headers(): void
    {
        PublicOfficial::factory()->create();
        $r = $this->getJson('/api/v1/officials');
        $r->assertSuccessful();
        $r->assertHeader('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=900');
    }
}
