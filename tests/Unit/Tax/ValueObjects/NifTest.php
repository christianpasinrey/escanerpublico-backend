<?php

namespace Tests\Unit\Tax\ValueObjects;

use InvalidArgumentException;
use Modules\Tax\ValueObjects\Nif;
use Tests\TestCase;

class NifTest extends TestCase
{
    public function test_validates_real_dni(): void
    {
        $this->assertTrue(Nif::isValid('45861020D'));
        $this->assertTrue(Nif::isValid('00000000T'));
        $this->assertTrue(Nif::isValid('12345678Z'));
    }

    public function test_rejects_dni_with_wrong_letter(): void
    {
        $this->assertFalse(Nif::isValid('45861020A'));
        $this->assertFalse(Nif::isValid('12345678A'));
    }

    public function test_validates_nie(): void
    {
        $this->assertTrue(Nif::isValid('X0000000T'));
        $this->assertTrue(Nif::isValid('Y0000000Z'));
    }

    public function test_validates_company_cif(): void
    {
        // Ejemplo CIF válido público: A58818501 (Mercadona)
        $this->assertTrue(Nif::isValid('A58818501'));
    }

    public function test_rejects_random_string(): void
    {
        $this->assertFalse(Nif::isValid('hello'));
        $this->assertFalse(Nif::isValid('123'));
    }

    public function test_constructor_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Nif('invalid');
    }

    public function test_is_personal_vs_company(): void
    {
        $this->assertTrue((new Nif('45861020D'))->isPersonal());
        $this->assertTrue((new Nif('X0000000T'))->isPersonal());
        $this->assertTrue((new Nif('A58818501'))->isCompany());
    }

    public function test_normalizes_lowercase_with_factory(): void
    {
        $nif = Nif::fromString('45861020d');
        $this->assertSame('45861020D', $nif->value);
    }
}
