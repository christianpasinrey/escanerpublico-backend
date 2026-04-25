<?php

namespace Database\Factories\Modules\Subsidies;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subsidies\Models\SubsidyGrant;
use Modules\Subsidies\Models\SubsidySnapshot;

/**
 * @extends Factory<SubsidySnapshot>
 */
class SubsidySnapshotFactory extends Factory
{
    protected $model = SubsidySnapshot::class;

    public function definition(): array
    {
        return [
            'subsidy_grant_id' => SubsidyGrant::factory(),
            'raw_payload' => ['id' => $this->faker->numberBetween(1, 999999)],
            'content_hash' => bin2hex(random_bytes(32)),
            'fetched_at' => now(),
        ];
    }
}
