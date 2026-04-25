<?php

namespace Tests\Feature\Officials;

use Modules\Officials\Services\CargoExtractor;
use Tests\TestCase;

class CargoExtractorTest extends TestCase
{
    private CargoExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new CargoExtractor;
    }

    public function test_extracts_appointment_with_don(): void
    {
        $r = $this->extractor->extract('Real Decreto 123/2026, de 12 de marzo, por el que se nombra a Don Juan Pérez García Director General de Tributos.');

        $this->assertNotNull($r);
        $this->assertSame('appointment', $r['event_type']);
        $this->assertSame('Don', $r['honorific']);
        $this->assertSame('Juan Pérez García', $r['full_name']);
        $this->assertStringContainsString('Director General de Tributos', $r['cargo']);
    }

    public function test_extracts_appointment_with_dona(): void
    {
        $r = $this->extractor->extract('Real Decreto 456/2026, por el que se nombra a Doña María López Sánchez Subsecretaria de Hacienda.');

        $this->assertNotNull($r);
        $this->assertSame('appointment', $r['event_type']);
        $this->assertSame('María López Sánchez', $r['full_name']);
    }

    public function test_extracts_cessation(): void
    {
        $r = $this->extractor->extract('Real Decreto 789/2026, por el que se dispone el cese de Don Antonio Ruiz como Director General de Patrimonio.');

        $this->assertNotNull($r);
        $this->assertSame('cessation', $r['event_type']);
        $this->assertSame('Antonio Ruiz', $r['full_name']);
        $this->assertStringContainsString('Director General de Patrimonio', $r['cargo']);
    }

    public function test_extracts_posession(): void
    {
        $r = $this->extractor->extract('Por la que toma posesión Doña Ana Sánchez García como Subsecretaria de Industria.');

        $this->assertNotNull($r);
        $this->assertSame('posession', $r['event_type']);
        $this->assertSame('Ana Sánchez García', $r['full_name']);
    }

    public function test_skips_collective_appointment(): void
    {
        $r = $this->extractor->extract('Resolución por la que se nombran funcionarios de carrera del Cuerpo Técnico de Hacienda.');
        $this->assertNull($r);
    }

    public function test_skips_promotion_collective(): void
    {
        $r = $this->extractor->extract('Resolución por la que se promueve a empleo superior a varios oficiales del Ejército.');
        $this->assertNull($r);
    }

    public function test_returns_null_on_unrelated_title(): void
    {
        $r = $this->extractor->extract('Orden DEF/372/2026 por la que se modifica el currículo de la enseñanza militar.');
        $this->assertNull($r);
    }

    public function test_returns_null_on_empty_or_null(): void
    {
        $this->assertNull($this->extractor->extract(null));
        $this->assertNull($this->extractor->extract(''));
        $this->assertNull($this->extractor->extract('   '));
    }

    public function test_normalize_strips_accents_and_lowercases(): void
    {
        $this->assertSame('juan perez garcia', CargoExtractor::normalize('Juan Pérez García'));
        $this->assertSame('maria lopez sanchez', CargoExtractor::normalize('  María   López   Sánchez  '));
        $this->assertSame('jose-luis rodriguez', CargoExtractor::normalize('José-Luis Rodríguez'));
    }

    public function test_uses_d_abbreviated_honorific(): void
    {
        $r = $this->extractor->extract('Resolución por la que se nombra a D. Pedro Martínez Director del Gabinete.');
        $this->assertNotNull($r);
        $this->assertSame('Pedro Martínez', $r['full_name']);
    }

    // ===== Tests con datos REALES del BOE (samples directos de prod) =====

    public function test_extracts_real_boe_format_cargo_first_with_lowercase_don(): void
    {
        $r = $this->extractor->extract('Resolución de 22 de diciembre de 2023, de la Universidad de Alcalá, por la que se nombra Catedrático de Universidad a don Pedro Cuesta Valiño.');

        $this->assertNotNull($r);
        $this->assertSame('appointment', $r['event_type']);
        $this->assertSame('Pedro Cuesta Valiño', $r['full_name']);
        $this->assertStringContainsString('Catedrático de Universidad', $r['cargo']);
    }

    public function test_extracts_profesor_titular_with_intermediate_clause(): void
    {
        $r = $this->extractor->extract('Resolución de 22 de diciembre de 2023, conjunta de la Universidad de Oviedo y el Servicio de Salud del Principado de Asturias, por la que se nombra Profesor Titular de Universidad, con plaza vinculada, a don Gonzalo Solís Sánchez.');

        $this->assertNotNull($r);
        $this->assertSame('appointment', $r['event_type']);
        $this->assertSame('Gonzalo Solís Sánchez', $r['full_name']);
        $this->assertStringContainsString('Profesor Titular de Universidad', $r['cargo']);
    }

    public function test_extracts_jubilation_notario_as_cessation(): void
    {
        $r = $this->extractor->extract('Resolución de 22 de diciembre de 2023, de la Dirección General de Seguridad Jurídica y Fe Pública, por la que se declara la jubilación del notario de Avilés don Tomás Domínguez Bautista.');

        $this->assertNotNull($r);
        $this->assertSame('cessation', $r['event_type']);
        $this->assertSame('Tomás Domínguez Bautista', $r['full_name']);
    }

    public function test_extracts_jubilation_dona_post_name(): void
    {
        $r = $this->extractor->extract('Resolución de 27 de diciembre de 2023, de la Dirección General para el Servicio Público de Justicia, por la que se declara la jubilación de doña María Victoria Gutiérrez Díaz, Fiscal con destino en la Sección Territorial de Málaga.');

        $this->assertNotNull($r);
        $this->assertSame('cessation', $r['event_type']);
        $this->assertSame('María Victoria Gutiérrez Díaz', $r['full_name']);
    }

    public function test_skips_libre_designacion(): void
    {
        $this->assertNull($this->extractor->extract('Resolución de 21 de diciembre de 2023, de la Subsecretaría, por la que se resuelve la convocatoria de libre designación, efectuada por Resolución de 13 de noviembre de 2023.'));
    }

    public function test_skips_plural_catedraticos(): void
    {
        $this->assertNull($this->extractor->extract('Resolución de 21 de diciembre de 2023, de la Universidad de Murcia, por la que se nombran Catedráticas y Catedráticos de Universidad.'));
    }

    public function test_skips_personal_funcionario_carrera(): void
    {
        $this->assertNull($this->extractor->extract('Resolución de 26 de diciembre de 2023, de la Secretaría de Estado de Función Pública, por la que se nombra personal funcionario de carrera, por el sistema de promoción interna.'));
    }
}
