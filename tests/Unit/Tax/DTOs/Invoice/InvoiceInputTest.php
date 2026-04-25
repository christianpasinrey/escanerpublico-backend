<?php

namespace Tests\Unit\Tax\DTOs\Invoice;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\DTOs\Invoice\ClientType;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use PHPUnit\Framework\TestCase;

class InvoiceInputTest extends TestCase
{
    /**
     * NIF válido para tests: 00000000T (DNI con dígito de control correcto).
     */
    private function validNif(): Nif
    {
        return new Nif('00000000T');
    }

    private function validLine(): InvoiceLineInput
    {
        return new InvoiceLineInput(
            description: 'Servicio',
            quantity: '1',
            unitPrice: new Money('100.00'),
        );
    }

    public function test_constructs_with_minimal_valid_data(): void
    {
        $input = new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );

        $this->assertCount(1, $input->lines);
        $this->assertTrue($input->isDomestic());
        $this->assertFalse($input->isIntracommunity());
    }

    public function test_rejects_empty_lines(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('al menos una línea');

        new InvoiceInput(
            lines: [],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );
    }

    public function test_rejects_wrong_scope_for_vat_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IVA');

        new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('EDN'), // wrong scope (irpf)
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );
    }

    public function test_rejects_wrong_scope_for_irpf_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IRPF');

        new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('IVA_GEN'), // wrong
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );
    }

    public function test_intracommunity_flag_for_eu_country(): void
    {
        $input = new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientCountry: 'FR',
        );

        $this->assertFalse($input->isDomestic());
        $this->assertTrue($input->isIntracommunity());
    }

    public function test_extracommunity_flag_for_non_eu(): void
    {
        $input = new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientCountry: 'US',
        );

        $this->assertFalse($input->isDomestic());
        $this->assertFalse($input->isIntracommunity());
    }

    public function test_rejects_invalid_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ISO');

        new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientCountry: 'ESP',
        );
    }

    public function test_default_client_type_is_empresa(): void
    {
        $input = new InvoiceInput(
            lines: [$this->validLine()],
            issuerNif: $this->validNif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );

        $this->assertSame(ClientType::EMPRESA, $input->clientType);
    }
}
