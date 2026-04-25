<?php

namespace Tests\Feature\Legislation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;
use Modules\Legislation\Models\BoeSummary;
use Modules\Legislation\Models\LegislationNorm;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legislation_norm_can_be_created_with_organization(): void
    {
        $org = Organization::factory()->create();
        $norm = LegislationNorm::factory()->for($org, 'organization')->create();

        $this->assertEquals($org->id, $norm->organization->id);
        $this->assertSame('BOE', $norm->source);
        $this->assertStringStartsWith('BOE-A-', $norm->external_id);
    }

    public function test_legislation_norm_unique_source_external(): void
    {
        LegislationNorm::factory()->create(['source' => 'BOE', 'external_id' => 'BOE-A-2024-1']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        LegislationNorm::factory()->create(['source' => 'BOE', 'external_id' => 'BOE-A-2024-1']);
    }

    public function test_boe_summary_unique_identificador(): void
    {
        BoeSummary::factory()->create(['source' => 'BOE', 'identificador' => 'BOE-S-2024-100']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        BoeSummary::factory()->create(['source' => 'BOE', 'identificador' => 'BOE-S-2024-100']);
    }

    public function test_boe_item_belongs_to_summary_and_organization(): void
    {
        $summary = BoeSummary::factory()->create();
        $org = Organization::factory()->create();
        $item = BoeItem::factory()
            ->for($summary, 'summary')
            ->for($org, 'organization')
            ->create();

        $this->assertEquals($summary->id, $item->summary->id);
        $this->assertEquals($org->id, $item->organization->id);
    }

    public function test_boe_summary_can_have_items(): void
    {
        $summary = BoeSummary::factory()->create();
        BoeItem::factory()->for($summary, 'summary')->count(5)->create();

        $this->assertCount(5, $summary->refresh()->items);
    }

    public function test_boe_summary_cascade_deletes_items(): void
    {
        $summary = BoeSummary::factory()->create();
        BoeItem::factory()->for($summary, 'summary')->count(3)->create();

        $summary->delete();

        $this->assertSame(0, BoeItem::count());
    }
}
