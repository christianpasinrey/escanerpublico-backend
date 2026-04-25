<?php

namespace Tests\Feature\Tax\Calculators\Invoice;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Invoice\EstimacionDirectaInvoice;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\Invoice\ClientType;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

/**
 * Golden tests para el calculator EstimacionDirectaInvoice
 * (régimen IVA general / criterio caja + IRPF EDN/EDS).
 *
 * Cada test cita la fuente legal y razona el importe esperado.
 */
class EstimacionDirectaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): EstimacionDirectaInvoice
    {
        return new EstimacionDirectaInvoice(
            new VatRateResolver,
            new IrpfRetentionResolver,
            new SurchargeEquivalenceResolver,
        );
    }

    private function nif(): Nif
    {
        return new Nif('00000000T');
    }

    /**
     * Caso golden: autónomo profesional EDN factura 1000 € a empresa.
     *
     *   Subtotal:   1000,00 €
     *   IVA 21 %:   +210,00 €  (art. 90 LIVA)
     *   Retención IRPF 15 %: −150,00 € (art. 95 RIRPF)
     *   Total a cobrar:  1060,00 €
     */
    public function test_autonomo_edn_factura_1000_euros_a_empresa_total_1060(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicios de consultoría',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                    vatRateType: VatRateType::GENERAL,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('1000.00', $result->subtotal->amount);
        $this->assertSame('210.00', $result->totalVat->amount);
        $this->assertSame('150.00', $result->totalIrpfRetention->amount);
        $this->assertSame('0.00', $result->totalSurchargeEquivalence->amount);
        $this->assertSame('1060.00', $result->totalToCharge->amount);
    }

    /**
     * Autónomo NUEVO (issuerNewActivityFlag=true) — retención 7 % primeros 3 años.
     *
     *   1000 + 21 % − 7 % = 1000 + 210 − 70 = 1140
     *
     * Fuente: DA 31ª RIRPF.
     */
    public function test_autonomo_nuevo_factura_1000_a_empresa_total_1140(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicios de consultoría',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                    vatRateType: VatRateType::GENERAL,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
            issuerNewActivityFlag: true,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('1000.00', $result->subtotal->amount);
        $this->assertSame('210.00', $result->totalVat->amount);
        $this->assertSame('70.00', $result->totalIrpfRetention->amount);
        $this->assertSame('1140.00', $result->totalToCharge->amount);
    }

    /**
     * Mix de dos líneas: servicio (500 € con retención) + libros (200 € sin
     * retención). IVA general 21 % al servicio, superreducido 4 % a libros.
     *
     *   IVA = 500 × 21 % + 200 × 4 % = 105 + 8 = 113
     *   Retención = 500 × 15 % = 75 (libros no retiene)
     *   Total = 700 + 113 − 75 = 738
     *
     * Fuente: art. 91.dos LIVA (4 % libros), art. 95 RIRPF.
     */
    public function test_factura_mixta_servicio_y_libros_total_738(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Asesoramiento fiscal',
                    quantity: '1',
                    unitPrice: new Money('500.00'),
                    vatRateType: VatRateType::GENERAL,
                    irpfRetentionApplies: true,
                ),
                new InvoiceLineInput(
                    description: 'Libros de fiscalidad',
                    quantity: '1',
                    unitPrice: new Money('200.00'),
                    vatRateType: VatRateType::SUPER_REDUCED,
                    irpfRetentionApplies: false,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('700.00', $result->subtotal->amount);
        $this->assertSame('113.00', $result->totalVat->amount); // 105 + 8
        $this->assertSame('75.00', $result->totalIrpfRetention->amount); // 15 % de 500
        $this->assertSame('738.00', $result->totalToCharge->amount);
    }

    /**
     * Cliente particular (consumidor final) → no retiene IRPF.
     *
     *   1000 + 21 % − 0 = 1210
     */
    public function test_factura_a_particular_no_retiene_total_1210(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicio',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::PARTICULAR,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('0.00', $result->totalIrpfRetention->amount);
        $this->assertSame('210.00', $result->totalVat->amount);
        $this->assertSame('1210.00', $result->totalToCharge->amount);
    }

    /**
     * Cliente acogido al Recargo de Equivalencia → emisor general añade RE.
     *
     *   1000 + 21 % + RE 5,2 % − 15 % = 1000 + 210 + 52 − 150 = 1112
     *
     * Nota: la spec dice 1212 pero el cálculo correcto es 1112. RE 5,2 % de
     * 1000 = 52. Subtotal 1000 + IVA 210 + RE 52 = 1262 bruto, menos
     * retención 150 = 1112.
     *
     * Fuente: art. 161 LIVA.
     */
    public function test_factura_a_cliente_re_total_con_recargo(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Mercancía',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                    vatRateType: VatRateType::GENERAL,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
            surchargeEquivalenceFlag: true,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('1000.00', $result->subtotal->amount);
        $this->assertSame('210.00', $result->totalVat->amount);
        $this->assertSame('52.00', $result->totalSurchargeEquivalence->amount);
        $this->assertSame('150.00', $result->totalIrpfRetention->amount);
        $this->assertSame('1112.00', $result->totalToCharge->amount);
    }

    /**
     * Cliente UE empresa con NIF-IVA → entrega intracomunitaria, IVA 0
     * por inversión sujeto pasivo (art. 25 LIVA). En MVP el calculator
     * presenta la factura sin IVA y añade nota INFO. Tampoco hay retención
     * IRPF en operaciones intracomunitarias (cliente no es retenedor ES).
     */
    public function test_factura_intracomunitaria_sin_iva_ni_retencion(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicio a empresa francesa',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
            clientCountry: 'FR',
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('1000.00', $result->subtotal->amount);
        $this->assertSame('0.00', $result->totalVat->amount);
        $this->assertSame('0.00', $result->totalIrpfRetention->amount);
        $this->assertSame('1000.00', $result->totalToCharge->amount);

        // Comprueba que hay una línea INFO mencionando la operación intracomunitaria.
        $hasInfoLine = false;
        foreach ($result->breakdown->lines as $line) {
            if ($line->category === BreakdownCategory::INFO
                && stripos($line->concept, 'intracomunitaria') !== false) {
                $hasInfoLine = true;
                break;
            }
        }
        $this->assertTrue($hasInfoLine, 'Falta línea INFO de operación intracomunitaria.');
    }

    /**
     * Criterio de caja: mismo desglose que general, pero la fuente legal
     * citada es art. 163 decies LIVA.
     */
    public function test_iva_caja_mismos_importes_que_general_pero_fuente_distinta(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicio',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_CAJA'),
            issuerIrpfRegime: RegimeCode::fromString('EDS'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('1060.00', $result->totalToCharge->amount);

        // Localiza la línea TAX de IVA y comprueba que su explicación refiere
        // al criterio de caja (art. 163 decies).
        $foundCajaExplanation = false;
        foreach ($result->breakdown->lines as $line) {
            if ($line->category === BreakdownCategory::TAX
                && $line->explanation !== null
                && stripos($line->explanation, 'caja') !== false) {
                $foundCajaExplanation = true;
                break;
            }
        }
        $this->assertTrue($foundCajaExplanation, 'IVA caja debe llevar nota de devengo por cobro.');
    }

    /**
     * Línea individual: el InvoiceLineResult guarda subtotal, vatRate, etc.
     */
    public function test_line_results_keep_per_line_detail(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'A',
                    quantity: '2',
                    unitPrice: new Money('100.00'),
                ),
                new InvoiceLineInput(
                    description: 'B',
                    quantity: '1',
                    unitPrice: new Money('300.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_GEN'),
            issuerIrpfRegime: RegimeCode::fromString('EDN'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::PARTICULAR,
        );

        $result = $this->calculator()->calculate($input);
        $this->assertCount(2, $result->lines);
        $this->assertSame('200.00', $result->lines[0]->subtotal->amount);
        $this->assertSame('300.00', $result->lines[1]->subtotal->amount);
        // El rate puede llegar como '21.00' (default fallback) o '21.0000' (catálogo);
        // verificamos que la conversión a decimal aplique correctamente.
        $this->assertSame('0.21000000', $result->lines[0]->vatRate->asDecimal());
    }
}
