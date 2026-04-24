<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;

/**
 * @extends Factory<ReprocessAtomRun>
 */
class ReprocessAtomRunFactory extends Factory
{
    protected $model = ReprocessAtomRun::class;

    public function definition(): array
    {
        return [
            'reprocess_run_id' => ReprocessRun::factory(),
            'atom_path' => 'atoms/' . $this->faker->unique()->numerify('########') . '.atom',
            'atom_hash' => sha1($this->faker->uuid()),
            'status' => $this->faker->randomElement(['pending', 'running', 'completed', 'failed']),
            'started_at' => null,
            'finished_at' => null,
            'entries_processed' => 0,
            'entries_failed' => 0,
            'error_message' => null,
        ];
    }
}
