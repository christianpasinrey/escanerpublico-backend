<?php

namespace Tests\Feature\Subsidies;

use Modules\Subsidies\Services\BeneficiarioParser;
use Tests\TestCase;

class BeneficiarioParserTest extends TestCase
{
    private BeneficiarioParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BeneficiarioParser;
    }

    public function test_parses_cif_with_letter_prefix(): void
    {
        [$nif, $name] = $this->parser->parse('B12345678 ACME CONSTRUCCIONES SL');
        $this->assertSame('B12345678', $nif);
        $this->assertSame('ACME CONSTRUCCIONES SL', $name);
    }

    public function test_parses_dni_with_letter_suffix(): void
    {
        [$nif, $name] = $this->parser->parse('12345678A JOSE LOPEZ GARCIA');
        $this->assertSame('12345678A', $nif);
        $this->assertSame('JOSE LOPEZ GARCIA', $name);
    }

    public function test_parses_nie(): void
    {
        [$nif, $name] = $this->parser->parse('X1234567L FOO BAR');
        $this->assertSame('X1234567L', $nif);
        $this->assertSame('FOO BAR', $name);
    }

    public function test_returns_null_nif_when_fully_redacted(): void
    {
        [$nif, $name] = $this->parser->parse('***1234** PERSONA FISICA');
        $this->assertNull($nif);
        $this->assertSame('PERSONA FISICA', $name);
    }

    public function test_returns_raw_when_no_pattern_match(): void
    {
        [$nif, $name] = $this->parser->parse('AYUNTAMIENTO X');
        $this->assertNull($nif);
        $this->assertSame('AYUNTAMIENTO X', $name);
    }

    public function test_handles_null_and_empty(): void
    {
        $this->assertSame([null, null], $this->parser->parse(null));
        $this->assertSame([null, null], $this->parser->parse(''));
        $this->assertSame([null, null], $this->parser->parse('   '));
    }

    public function test_strips_whitespace_around(): void
    {
        [$nif, $name] = $this->parser->parse('  B12345678   ACME SL  ');
        $this->assertSame('B12345678', $nif);
        $this->assertSame('ACME SL', $name);
    }
}
