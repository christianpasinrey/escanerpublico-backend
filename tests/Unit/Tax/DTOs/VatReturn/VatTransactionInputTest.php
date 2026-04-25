<?php

namespace Tests\Unit\Tax\DTOs\VatReturn;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Tax\DTOs\VatReturn\VatTransactionCategory;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests para VatTransactionInput.
 */
class VatTransactionInputTest extends TestCase
{
    public function test_outgoing_transaction_with_general_vat_is_valid(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('2000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('420.00'),
            description: 'Factura emitida cliente A',
        );

        $this->assertSame('outgoing', $tx->direction->value);
        $this->assertSame('2000.00', $tx->base->amount);
        $this->assertSame('420.00', $tx->vatAmount->amount);
        $this->assertSame(VatTransactionCategory::DOMESTIC, $tx->category);
        $this->assertNull($tx->paidDate);
    }

    public function test_incoming_transaction_with_reduced_vat_is_valid(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::INCOMING,
            date: CarbonImmutable::create(2025, 5, 20),
            base: new Money('500.00'),
            vatRate: TaxRate::fromPercentage('10.00'),
            vatAmount: new Money('50.00'),
            description: 'Factura recibida proveedor B',
        );

        $this->assertSame('incoming', $tx->direction->value);
        $this->assertSame('50.00', $tx->vatAmount->amount);
    }

    public function test_throws_when_description_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('21.00'),
            description: '',
        );
    }

    public function test_throws_when_base_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('-100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('-21.00'),
            description: 'Test',
        );
    }

    public function test_throws_when_vat_amount_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('-21.00'),
            description: 'Test',
        );
    }

    public function test_throws_when_paid_date_is_before_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no puede ser anterior/');
        new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('21.00'),
            description: 'Test',
            paidDate: CarbonImmutable::create(2025, 4, 10),
        );
    }

    public function test_throws_when_vat_amount_inconsistent_with_base_times_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no coherente/');
        new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('1000.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('100.00'), // Debería ser 210.00
            description: 'Test',
        );
    }

    public function test_accepts_one_cent_rounding_tolerance(): void
    {
        // base 33.33 × 21 % = 6.99930 → redondeado a 7.00 (pero 6.99 también vale)
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('33.33'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('7.00'), // ±0.01 de 6.99
            description: 'Test redondeo',
        );

        $this->assertSame('7.00', $tx->vatAmount->amount);
    }

    public function test_is_paid_by_returns_true_when_cutoff_after_paid_date(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 3, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('21.00'),
            description: 'Test',
            paidDate: CarbonImmutable::create(2025, 4, 5),
        );

        $this->assertTrue($tx->isPaidBy(CarbonImmutable::create(2025, 6, 30)));
        $this->assertFalse($tx->isPaidBy(CarbonImmutable::create(2025, 3, 31)));
    }

    public function test_forced_accrual_date_is_31_dec_following_year(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 6, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('21.00'),
            description: 'Test',
        );

        $forced = $tx->forcedAccrualDate();
        $this->assertSame(2026, $forced->year);
        $this->assertSame(12, $forced->month);
        $this->assertSame(31, $forced->day);
    }

    public function test_serializes_to_json_with_all_fields(): void
    {
        $tx = new VatTransactionInput(
            direction: VatTransactionDirection::OUTGOING,
            date: CarbonImmutable::create(2025, 4, 15),
            base: new Money('100.00'),
            vatRate: TaxRate::fromPercentage('21.00'),
            vatAmount: new Money('21.00'),
            description: 'Test',
            category: VatTransactionCategory::INTRACOM,
            surchargeEquivalenceAmount: new Money('5.20'),
        );

        $json = $tx->jsonSerialize();
        $this->assertSame('outgoing', $json['direction']);
        $this->assertSame('2025-04-15', $json['date']);
        $this->assertSame('intracom', $json['category']);
        $this->assertNotNull($json['surcharge_equivalence_amount']);
    }
}
