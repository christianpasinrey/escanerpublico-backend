<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Organization;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'identifier' => 'L0' . $this->faker->unique()->numerify('#########'),
            'nif' => 'P' . $this->faker->numerify('########') . 'H',
            'type_code' => $this->faker->randomElement(['1', '2', '3']),
            'buyer_profile_uri' => null,
            'activity_code' => null,
            'platform_id' => null,
        ];
    }
}
