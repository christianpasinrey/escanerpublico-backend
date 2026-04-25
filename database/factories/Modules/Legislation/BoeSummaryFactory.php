<?php

namespace Database\Factories\Modules\Legislation;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legislation\Models\BoeSummary;

/**
 * @extends Factory<BoeSummary>
 */
class BoeSummaryFactory extends Factory
{
    protected $model = BoeSummary::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2026);
        $num = $this->faker->unique()->numberBetween(1, 365);

        return [
            'source' => 'BOE',
            'identificador' => "BOE-S-{$year}-{$num}",
            'fecha_publicacion' => $this->faker->dateTimeBetween('-1 year'),
            'numero' => (string) $num,
            'url_pdf' => "https://www.boe.es/boe/dias/{$year}/test/pdfs/BOE-S-{$year}-{$num}.pdf",
            'pdf_size_bytes' => $this->faker->numberBetween(100000, 5000000),
            'raw_payload' => ['identificador' => "BOE-S-{$year}-{$num}"],
            'content_hash' => bin2hex(random_bytes(32)),
            'ingested_at' => now(),
        ];
    }
}
