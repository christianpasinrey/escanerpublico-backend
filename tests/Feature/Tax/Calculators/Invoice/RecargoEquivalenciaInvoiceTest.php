<?php

namespace Tests\Feature\Tax\Calculators\Invoice;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Invoice\RecargoEquivalenciaInvoice;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\Invoice\ClientType;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

/**
 * El minorista en Recargo de Equivalencia repercute IVA igual que en
 * régimen general; lo que cambia es que NO presenta declaraciones IVA
 * (lo abonó al proveedor al comprar). El calculator añade nota INFO.
 */
class RecargoEquivalenciaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): RecargoEquivalenciaInvoice
    {
        return new RecargoEquivalenciaInvoice(
            new VatRateResolver,
            new IrpfRetentionResolver,
        );
    }

    private function nif(): Nif
    {
        return new Nif('00000000T');
    }

    public function test_minorista_re_factura_a_particular_repercute_iva_general(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Camiseta',
                    quantity: '1',
                    unitPrice: new Money('50.00'),
                    vatRateType: VatRateType::GENERAL,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_RE'),
            issuerIrpfRegime: RegimeCode::fromString('EDS'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::PARTICULAR,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('50.00', $result->subtotal->amount);
        $this->assertSame('10.50', $result->totalVat->amount);
        $this->assertSame('60.50', $result->totalToCharge->amount);

        $hasReNote = false;
        foreach ($result->breakdown->lines as $line) {
            if ($line->category === BreakdownCategory::INFO
                && stripos($line->concept, 'Recargo de Equivalencia') !== false) {
                $hasReNote = true;
                break;
            }
        }
        $this->assertTrue($hasReNote, 'Falta línea INFO sobre RE.');
    }

    public function test_minorista_re_no_aplica_recargo_emitir_porque_es_re_que_recibe(): void
    {
        // El RE lo paga el minorista al PROVEEDOR cuando compra.
        // Cuando emite no se añade RE (a diferencia de IVA general que factura
        // a un cliente RE), aunque el flag esté activo el cliente.
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Mercancía',
                    quantity: '1',
                    unitPrice: new Money('100.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_RE'),
            issuerIrpfRegime: RegimeCode::fromString('EDS'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
            surchargeEquivalenceFlag: true, // ignorado en este calculator
        );

        $result = $this->calculator()->calculate($input);

        // El minorista no añade RE al emitir, sólo lo paga al comprar.
        $this->assertSame('0.00', $result->totalSurchargeEquivalence->amount);
        // 100 + 21 = 121 - 15 = 106.
        $this->assertSame('106.00', $result->totalToCharge->amount);
    }
}
