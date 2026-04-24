<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'nif' => 'B'.$this->faker->unique()->numerify('########'),
            'identifier' => 'B'.$this->faker->unique()->numerify('########'),
        ];
    }
}
