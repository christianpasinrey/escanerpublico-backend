<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractSnapshot;

/**
 * @extends Factory<ContractSnapshot>
 */
class ContractSnapshotFactory extends Factory
{
    protected $model = ContractSnapshot::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'entry_updated_at' => $this->faker->unique()->dateTime(),
            'status_code' => $this->faker->randomElement(['PUB', 'EV', 'ADJ', 'RES', 'ANUL']),
            'content_hash' => sha1($this->faker->uuid()),
            'payload' => ['sample' => true],
            'source_atom' => 'fake.atom',
            'ingested_at' => now(),
        ];
    }
}
