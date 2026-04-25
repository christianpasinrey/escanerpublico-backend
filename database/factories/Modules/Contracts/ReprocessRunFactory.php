<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ReprocessRun;

/**
 * @extends Factory<ReprocessRun>
 */
class ReprocessRunFactory extends Factory
{
    protected $model = ReprocessRun::class;

    public function definition(): array
    {
        return [
            'name' => 'run-'.$this->faker->unique()->numerify('#####'),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed', 'cancelled']),
            'started_at' => null,
            'finished_at' => null,
            'total_atoms' => $this->faker->numberBetween(0, 100),
            'processed_atoms' => 0,
            'total_entries' => 0,
            'failed_entries' => 0,
            'config' => ['source' => 'fake'],
        ];
    }
}
