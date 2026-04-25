<?php

namespace Database\Factories\Modules\Legislation;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legislation\Models\LegislationNorm;

/**
 * @extends Factory<LegislationNorm>
 */
class LegislationNormFactory extends Factory
{
    protected $model = LegislationNorm::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2018, 2026);
        $serial = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'source' => 'BOE',
            'external_id' => "BOE-A-{$year}-{$serial}",
            'ambito_code' => '1',
            'ambito_text' => 'Estatal',
            'departamento_code' => (string) $this->faker->numberBetween(1000, 9999),
            'departamento_text' => $this->faker->randomElement([
                'Ministerio de Hacienda',
                'Ministerio de Defensa',
                'Ministerio de la Presidencia',
                'Jefatura del Estado',
            ]),
            'rango_code' => $this->faker->randomElement(['1300', '1310', '1320', '1330']),
            'rango_text' => $this->faker->randomElement(['Ley', 'Real Decreto Legislativo', 'Real Decreto', 'Orden']),
            'numero_oficial' => $this->faker->numberBetween(1, 50).'/'.$year,
            'titulo' => $this->faker->sentence(20),
            'fecha_disposicion' => $this->faker->dateTimeBetween('-5 years'),
            'fecha_publicacion' => $this->faker->dateTimeBetween('-5 years'),
            'fecha_vigencia' => $this->faker->dateTimeBetween('-5 years'),
            'vigencia_agotada' => false,
            'estado_consolidacion_code' => '3',
            'estado_consolidacion_text' => 'Finalizado',
            'url_eli' => "https://www.boe.es/eli/es/test/{$year}/{$serial}",
            'url_html_consolidada' => "https://www.boe.es/buscar/act.php?id=BOE-A-{$year}-{$serial}",
            'content_hash' => bin2hex(random_bytes(32)),
            'ingested_at' => now(),
        ];
    }
}
