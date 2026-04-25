<?php

namespace Tests\Unit\Tax\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Ingestion\IaeImporter;
use Tests\TestCase;

class IaeImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_iae_section_1_hierarchy_from_array(): void
    {
        $rows = [
            ['code' => '6', 'level' => 1, 'section' => '1', 'name' => 'Comercio, restaurantes y hospedaje', 'parent_code' => null],
            ['code' => '67', 'level' => 2, 'section' => '1', 'name' => 'Servicio de alimentación', 'parent_code' => '6'],
            ['code' => '671', 'level' => 3, 'section' => '1', 'name' => 'Servicios en restaurantes', 'parent_code' => '67'],
            ['code' => '6713', 'level' => 4, 'section' => '1', 'name' => 'De tres tenedores', 'parent_code' => '671'],
        ];

        $stats = app(IaeImporter::class)->importFromArray($rows);

        $this->assertSame(4, $stats['inserted']);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'iae',
            'code' => '6713',
            'parent_code' => '671',
            'section' => '1',
            'level' => 4,
        ]);
    }

    public function test_imports_section_2_professionals(): void
    {
        $stats = app(IaeImporter::class)->importFromArray([
            ['code' => '8993', 'level' => 4, 'section' => '2', 'name' => 'Profesionales auxiliares de servicios financieros y jurídicos', 'parent_code' => '899'],
        ]);

        $this->assertSame(1, $stats['inserted']);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'iae',
            'code' => '8993',
            'section' => '2',
        ]);
    }

    public function test_imports_real_fixture_committed_with_aproximate_size(): void
    {
        $path = base_path('app/Modules/Tax/Ingestion/data/iae_seed.json');
        $this->assertFileExists($path);

        $stats = app(IaeImporter::class)->importFromJson($path);

        // Esperamos > 50 epígrafes en el fixture committeado
        $this->assertGreaterThan(50, $stats['inserted']);

        // Algunas verificaciones puntuales
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'iae',
            'code' => '6471',
            'name' => 'Comercio al por menor de cualquier clase de productos alimenticios y de bebidas en establecimientos con vendedor',
        ]);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'iae',
            'code' => '6531',
            'name' => 'Comercio al por menor de muebles',
        ]);
    }

    public function test_idempotent_reimport(): void
    {
        $rows = [
            ['code' => '6', 'level' => 1, 'section' => '1', 'name' => 'Comercio', 'parent_code' => null],
        ];

        $importer = app(IaeImporter::class);
        $importer->importFromArray($rows);
        $second = $importer->importFromArray($rows);

        $this->assertSame(0, $second['inserted']);
        $this->assertSame(1, $second['skipped']);
    }

    public function test_html_importer_extracts_codes_via_regex(): void
    {
        $html = <<<'HTML'
        <ul>
            <li>6 Comercio, restaurantes y hospedaje
                <ul>
                    <li>67 Servicio de alimentación
                        <ul>
                            <li>671 Servicios en restaurantes</li>
                            <li>672 Cafeterías</li>
                        </ul>
                    </li>
                </ul>
            </li>
        </ul>
        HTML;

        $stats = app(IaeImporter::class)->importFromHtml($html, year: 1992);

        $this->assertGreaterThanOrEqual(2, $stats['inserted'] + $stats['updated']);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'iae',
            'code' => '672',
        ]);
    }

    public function test_throws_when_json_file_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        app(IaeImporter::class)->importFromJson('/no/such/iae.json');
    }
}
