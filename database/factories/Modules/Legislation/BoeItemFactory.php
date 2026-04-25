<?php

namespace Database\Factories\Modules\Legislation;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legislation\Models\BoeItem;
use Modules\Legislation\Models\BoeSummary;

/**
 * @extends Factory<BoeItem>
 */
class BoeItemFactory extends Factory
{
    protected $model = BoeItem::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2026);
        $serial = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'summary_id' => BoeSummary::factory(),
            'source' => 'BOE',
            'external_id' => "BOE-A-{$year}-{$serial}",
            'control' => "{$year}/".$this->faker->numberBetween(1, 9999),
            'seccion_code' => '1',
            'seccion_nombre' => 'I. Disposiciones generales',
            'departamento_code' => (string) $this->faker->numberBetween(1000, 9999),
            'departamento_nombre' => 'MINISTERIO DE TEST',
            'epigrafe' => 'Pruebas',
            'titulo' => $this->faker->sentence(15),
            'url_pdf' => "https://www.boe.es/boe/dias/test/pdfs/BOE-A-{$year}-{$serial}.pdf",
            'pdf_size_bytes' => $this->faker->numberBetween(50000, 1000000),
            'pagina_inicial' => (string) $this->faker->numberBetween(1, 50000),
            'pagina_final' => (string) $this->faker->numberBetween(50001, 60000),
            'url_html' => "https://www.boe.es/diario_boe/txt.php?id=BOE-A-{$year}-{$serial}",
            'url_xml' => "https://www.boe.es/diario_boe/xml.php?id=BOE-A-{$year}-{$serial}",
            'fecha_publicacion' => $this->faker->dateTimeBetween('-1 year'),
            'content_hash' => bin2hex(random_bytes(32)),
        ];
    }
}
