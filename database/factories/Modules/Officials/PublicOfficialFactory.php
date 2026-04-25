<?php

namespace Database\Factories\Modules\Officials;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Officials\Models\PublicOfficial;

/**
 * @extends Factory<PublicOfficial>
 */
class PublicOfficialFactory extends Factory
{
    protected $model = PublicOfficial::class;

    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'full_name' => $name,
            'normalized_name' => mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name).'_'.$this->faker->unique()->numberBetween(1, 999999),
            'honorific' => $this->faker->randomElement(['Don', 'Doña', null]),
            'appointments_count' => 0,
        ];
    }
}
