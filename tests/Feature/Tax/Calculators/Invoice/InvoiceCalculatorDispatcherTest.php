<?php

namespace Tests\Feature\Tax\Calculators\Invoice;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Tax\Calculators\Invoice\InvoiceCalculator;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

class InvoiceCalculatorDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function dispatcher(): InvoiceCalculator
    {
        return new InvoiceCalculator(
            new VatRateResolver,
            new IrpfRetentionResolver,
            new SurchargeEquivalenceResolver,
        );
    }

    private function inputWithVatRegime(string $vatCode): InvoiceInput
    {
        return new InvoiceInput(
            lines: [
                new InvoiceLineInput('X', '1', new Money('100.00')),
            ],
            issuerNif: new Nif('00000000T'),
            issuerVatRegime: RegimeCode::fromString($vatCode),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
        );
    }

    public function test_dispatches_iva_gen_to_estimacion_directa(): void
    {
        $r = $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_GEN'));
        $this->assertSame('100.00', $r->subtotal->amount);
        $this->assertSame('21.00', $r->totalVat->amount);
    }

    public function test_dispatches_iva_caja_to_estimacion_directa(): void
    {
        $r = $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_CAJA'));
        $this->assertSame('21.00', $r->totalVat->amount);
    }

    public function test_dispatches_iva_simple_to_estimacion_objetiva(): void
    {
        $r = $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_SIMPLE'));
        // Estimacion objetiva: subtotal+IVA, sin RE.
        $this->assertSame('21.00', $r->totalVat->amount);
        $this->assertSame('0.00', $r->totalSurchargeEquivalence->amount);
    }

    public function test_dispatches_iva_re_to_recargo_equivalencia(): void
    {
        $r = $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_RE'));
        $this->assertSame('21.00', $r->totalVat->amount);
        // RE no añade recargo al emitir.
        $this->assertSame('0.00', $r->totalSurchargeEquivalence->amount);
    }

    public function test_throws_for_iva_regime_outside_mvp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fuera del alcance');

        $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_REBU'));
    }

    public function test_throws_for_oss_regime_outside_mvp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->dispatcher()->calculate($this->inputWithVatRegime('IVA_OSS'));
    }
}
