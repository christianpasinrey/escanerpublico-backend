<?php

namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Tests\TestCase;

class OrganizationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_columns_fillable(): void
    {
        $o = Organization::factory()->create([
            'buyer_profile_uri' => 'https://example/profile',
            'activity_code' => '1',
            'platform_id' => '31071580150918',
        ]);
        $this->assertEquals('https://example/profile', $o->buyer_profile_uri);
        $this->assertEquals('1', $o->activity_code);
        $this->assertEquals('31071580150918', $o->platform_id);
    }
}
