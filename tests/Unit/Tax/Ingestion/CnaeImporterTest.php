<?php

namespace Tests\Unit\Tax\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Ingestion\CnaeImporter;
use Modules\Tax\Models\EconomicActivity;
use Tests\TestCase;

class CnaeImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_section_division_class_subclass_levels_from_array(): void
    {
        $rows = [
            ['code' => 'A', 'name' => 'Agricultura, ganadería, silvicultura y pesca'],
            ['code' => '01', 'name' => 'Agricultura, ganadería, caza y servicios relacionados'],
            ['code' => '011', 'name' => 'Cultivos no perennes'],
            ['code' => '0111', 'name' => 'Cultivo de cereales'],
            ['code' => '01110', 'name' => 'Cultivo de cereales (excepto arroz)'],
        ];

        $stats = app(CnaeImporter::class)->importFromArray($rows);

        $this->assertSame(5, $stats['inserted']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame(0, $stats['skipped']);

        $this->assertDatabaseHas('economic_activities', [
            'system' => 'cnae',
            'code' => 'A',
            'level' => 1,
            'parent_code' => null,
            'section' => 'A',
        ]);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'cnae',
            'code' => '01',
            'level' => 2,
            'parent_code' => 'A',
            'section' => 'A',
        ]);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'cnae',
            'code' => '01110',
            'level' => 5,
            'parent_code' => '0111',
            'section' => 'A',
        ]);
    }

    public function test_idempotent_reimport_marks_unchanged_as_skipped(): void
    {
        $rows = [
            ['code' => 'F', 'name' => 'Construcción'],
            ['code' => '41', 'name' => 'Construcción de edificios'],
        ];

        $importer = app(CnaeImporter::class);
        $first = $importer->importFromArray($rows);
        $second = $importer->importFromArray($rows);

        $this->assertSame(2, $first['inserted']);
        $this->assertSame(0, $second['inserted']);
        $this->assertSame(2, $second['skipped']);
        $this->assertSame(0, $second['updated']);
    }

    public function test_modified_row_marks_as_updated(): void
    {
        $importer = app(CnaeImporter::class);
        $importer->importFromArray([
            ['code' => 'F', 'name' => 'Construcción'],
        ]);

        $stats = $importer->importFromArray([
            ['code' => 'F', 'name' => 'Construcción (renombrada)'],
        ]);

        $this->assertSame(1, $stats['updated']);
        $this->assertDatabaseHas('economic_activities', [
            'system' => 'cnae',
            'code' => 'F',
            'name' => 'Construcción (renombrada)',
        ]);
    }

    public function test_invalid_codes_are_skipped(): void
    {
        $stats = app(CnaeImporter::class)->importFromArray([
            ['code' => '', 'name' => 'Empty'],
            ['code' => 'XYZQ', 'name' => 'Invalid (4 letras)'],
            ['code' => '0123456', 'name' => 'Demasiados dígitos'],
            ['code' => 'F', 'name' => 'Construcción'],
        ]);

        $this->assertSame(1, $stats['inserted']);
        $this->assertSame(3, $stats['skipped']);
    }

    public function test_parent_code_is_correctly_calculated_for_each_level(): void
    {
        app(CnaeImporter::class)->importFromArray([
            ['code' => 'I', 'name' => 'Hostelería'],
            ['code' => '55', 'name' => 'Servicios de alojamiento'],
            ['code' => '551', 'name' => 'Hoteles y alojamientos similares'],
            ['code' => '5510', 'name' => 'Hoteles y alojamientos similares'],
            ['code' => '55100', 'name' => 'Hoteles y alojamientos similares'],
        ]);

        $hotels = EconomicActivity::query()
            ->where('system', 'cnae')
            ->where('code', '55100')
            ->first();

        $this->assertNotNull($hotels);
        $this->assertSame(5, $hotels->level);
        $this->assertSame('5510', $hotels->parent_code);
        $this->assertSame('I', $hotels->section);
    }

    public function test_throws_when_xlsx_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        app(CnaeImporter::class)->importFromXlsx('/no/such/file.xlsx');
    }

    public function test_uses_year_field_for_uniqueness(): void
    {
        $importer = app(CnaeImporter::class);
        $importer->importFromArray([['code' => 'A', 'name' => 'CNAE-2025 Agricultura']], year: 2025);
        $importer->importFromArray([['code' => 'A', 'name' => 'CNAE-2009 Agricultura']], year: 2009);

        $this->assertSame(2, EconomicActivity::query()->where('code', 'A')->count());
    }
}
