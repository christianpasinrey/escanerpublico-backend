<?php

namespace Tests\Unit\Tax\DTOs\Invoice;

use InvalidArgumentException;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class InvoiceLineInputTest extends TestCase
{
    public function test_calculates_subtotal_with_bcmath(): void
    {
        $line = new InvoiceLineInput(
            description: 'Hora consultoría',
            quantity: '10',
            unitPrice: new Money('50.00'),
        );

        $this->assertSame('500.00', $line->subtotal()->amount);
    }

    public function test_subtotal_with_decimal_quantity(): void
    {
        $line = new InvoiceLineInput(
            description: 'Servicio',
            quantity: '1.5',
            unitPrice: new Money('100.00'),
        );

        $this->assertSame('150.00', $line->subtotal()->amount);
    }

    public function test_rejects_empty_description(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('descripción');

        new InvoiceLineInput(
            description: '',
            quantity: '1',
            unitPrice: new Money('10.00'),
        );
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceLineInput(
            description: 'X',
            quantity: '0',
            unitPrice: new Money('10.00'),
        );
    }

    public function test_rejects_negative_unit_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InvoiceLineInput(
            description: 'X',
            quantity: '1',
            unitPrice: new Money('-10.00'),
        );
    }

    public function test_irpf_retention_applies_default_true(): void
    {
        $line = new InvoiceLineInput(
            description: 'Servicio',
            quantity: '1',
            unitPrice: new Money('100.00'),
        );

        $this->assertTrue($line->irpfRetentionApplies);
    }

    public function test_can_disable_irpf_retention_per_line(): void
    {
        $line = new InvoiceLineInput(
            description: 'Libros',
            quantity: '1',
            unitPrice: new Money('20.00'),
            vatRateType: VatRateType::SUPER_REDUCED,
            irpfRetentionApplies: false,
        );

        $this->assertFalse($line->irpfRetentionApplies);
    }

    public function test_json_serialization_includes_all_fields(): void
    {
        $line = new InvoiceLineInput(
            description: 'Hora',
            quantity: '2',
            unitPrice: new Money('50.00'),
            vatRateType: VatRateType::GENERAL,
        );

        $json = $line->jsonSerialize();
        $this->assertSame('Hora', $json['description']);
        $this->assertSame('2', $json['quantity']);
        $this->assertSame('general', $json['vat_rate_type']);
    }
}
