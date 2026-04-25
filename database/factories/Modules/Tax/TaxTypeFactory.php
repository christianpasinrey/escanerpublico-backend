<?php

namespace Database\Factories\Modules\Tax;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxType;

/**
 * @extends Factory<TaxType>
 */
class TaxTypeFactory extends Factory
{
    protected $model = TaxType::class;

    public function definition(): array
    {
        return [
            'code' => 'TEST_'.strtoupper($this->faker->unique()->bothify('???###')),
            'scope' => Scope::State->value,
            'levy_type' => LevyType::Impuesto->value,
            'name' => $this->faker->sentence(3),
            'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-23186',
            'region_code' => null,
            'municipality_id' => null,
            'editorial_md' => $this->faker->paragraph(),
        ];
    }

    public function tasa(): self
    {
        return $this->state(fn () => ['levy_type' => LevyType::Tasa->value]);
    }

    public function regional(string $regionCode): self
    {
        return $this->state(fn () => [
            'scope' => Scope::Regional->value,
            'region_code' => $regionCode,
        ]);
    }
}
