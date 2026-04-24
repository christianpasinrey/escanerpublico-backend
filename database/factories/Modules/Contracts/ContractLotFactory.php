<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;

/**
 * @extends Factory<ContractLot>
 */
class ContractLotFactory extends Factory
{
    protected $model = ContractLot::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'lot_number' => $this->faker->unique()->numberBetween(1, 10000),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'tipo_contrato_code' => $this->faker->randomElement(['1', '2', '3']),
            'cpv_codes' => [$this->faker->numerify('########')],
            'budget_with_tax' => $this->faker->randomFloat(2, 1000, 500000),
            'budget_without_tax' => $this->faker->randomFloat(2, 800, 400000),
            'estimated_value' => $this->faker->randomFloat(2, 1000, 500000),
            'duration' => $this->faker->numberBetween(1, 36),
            'duration_unit' => 'MON',
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'nuts_code' => 'ES'.$this->faker->numerify('###'),
            'lugar_ejecucion' => $this->faker->city(),
        ];
    }
}
