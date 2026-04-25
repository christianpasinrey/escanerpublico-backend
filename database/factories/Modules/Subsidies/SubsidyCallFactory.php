<?php

namespace Database\Factories\Modules\Subsidies;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subsidies\Models\SubsidyCall;

/**
 * @extends Factory<SubsidyCall>
 */
class SubsidyCallFactory extends Factory
{
    protected $model = SubsidyCall::class;

    public function definition(): array
    {
        return [
            'source' => 'BDNS',
            'external_id' => $this->faker->unique()->numberBetween(100000, 9999999),
            'numero_convocatoria' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'organization_id' => null,
            'description' => $this->faker->sentence(20),
            'description_cooficial' => null,
            'reception_date' => $this->faker->dateTimeBetween('-2 years'),
            'nivel1' => $this->faker->randomElement(['LOCAL', 'AUTONOMICA', 'ESTATAL']),
            'nivel2' => $this->faker->company(),
            'nivel3' => $this->faker->company(),
            'codigo_invente' => null,
            'is_mrr' => $this->faker->boolean(20),
            'content_hash' => bin2hex(random_bytes(32)),
            'ingested_at' => now(),
        ];
    }
}
