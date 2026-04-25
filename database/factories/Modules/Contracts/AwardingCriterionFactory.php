<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\ContractLot;

/**
 * @extends Factory<AwardingCriterion>
 */
class AwardingCriterionFactory extends Factory
{
    protected $model = AwardingCriterion::class;

    public function definition(): array
    {
        return [
            'contract_lot_id' => ContractLot::factory(),
            'type_code' => $this->faker->randomElement(['A', 'B', 'C']),
            'subtype_code' => null,
            'description' => $this->faker->sentence(),
            'note' => null,
            'weight_numeric' => $this->faker->randomFloat(2, 0, 100),
            'sort_order' => $this->faker->unique()->numberBetween(1, 10000),
        ];
    }
}
