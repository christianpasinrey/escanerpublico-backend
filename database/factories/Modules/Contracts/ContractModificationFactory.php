<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractModification;

/**
 * @extends Factory<ContractModification>
 */
class ContractModificationFactory extends Factory
{
    protected $model = ContractModification::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'type' => $this->faker->randomElement(['modification', 'extension', 'cancellation', 'assignment', 'annulment']),
            'issue_date' => $this->faker->unique()->date(),
            'effective_date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
            'amount_delta' => $this->faker->randomFloat(2, -10000, 50000),
            'new_end_date' => $this->faker->date(),
            'related_notice_id' => null,
        ];
    }
}
