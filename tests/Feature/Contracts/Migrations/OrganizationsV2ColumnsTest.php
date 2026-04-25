<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_has_v2_columns(): void
    {
        foreach (['buyer_profile_uri', 'activity_code', 'platform_id'] as $c) {
            $this->assertTrue(Schema::hasColumn('organizations', $c), "Missing {$c}");
        }
    }
}
