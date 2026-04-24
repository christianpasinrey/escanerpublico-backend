<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'external_id' => 'https://contrataciondelestado.es/entry/' . $this->faker->unique()->numerify('########'),
            'expediente' => $this->faker->bothify('EXP-####'),
            'status_code' => $this->faker->randomElement(['PRE', 'PUB', 'EV', 'ADJ', 'RES', 'ANUL']),
            'objeto' => $this->faker->sentence(),
            'tipo_contrato_code' => $this->faker->randomElement(['1', '2', '3']),
            'importe_con_iva' => $this->faker->randomFloat(2, 1000, 500000),
            'importe_sin_iva' => $this->faker->randomFloat(2, 800, 400000),
            'organization_id' => Organization::factory(),
            'synced_at' => now(),
        ];
    }
}
