<?php

namespace Database\Factories\Modules\Subsidies;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Subsidies\Models\SubsidyGrant;

/**
 * @extends Factory<SubsidyGrant>
 */
class SubsidyGrantFactory extends Factory
{
    protected $model = SubsidyGrant::class;

    public function definition(): array
    {
        $grantDate = $this->faker->dateTimeBetween('-3 years');

        return [
            'source' => 'BDNS',
            'external_id' => $this->faker->unique()->numberBetween(1000000, 999999999),
            'cod_concesion' => 'SB'.$this->faker->unique()->numberBetween(100000000, 999999999),
            'call_id' => null,
            'external_call_id' => null,
            'organization_id' => null,
            'company_id' => null,
            'beneficiario_raw' => $this->faker->bothify('B######## ').$this->faker->company(),
            'beneficiario_nif' => $this->faker->bothify('B########'),
            'beneficiario_name' => $this->faker->company(),
            'grant_date' => $grantDate,
            'amount' => $this->faker->randomFloat(2, 500, 500000),
            'ayuda_equivalente' => $this->faker->randomFloat(2, 500, 500000),
            'instrumento' => $this->faker->randomElement([
                'SUBVENCIÓN',
                'ENTREGA DINERARIA SIN CONTRAPRESTACIÓN',
                'PRÉSTAMO',
                'AVAL',
            ]),
            'url_br' => null,
            'tiene_proyecto' => false,
            'id_persona' => $this->faker->numberBetween(100000, 99999999),
            'fecha_alta' => $grantDate,
            'content_hash' => bin2hex(random_bytes(32)),
            'ingested_at' => now(),
        ];
    }
}
