<?php

namespace Tests\Feature\Tax\Calculators\Invoice;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tax\Calculators\Invoice\EstimacionObjetivaInvoice;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\Invoice\ClientType;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\Models\ActivityRegimeMapping;
use Modules\Tax\Models\EconomicActivity;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Tests\TestCase;

class EstimacionObjetivaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): EstimacionObjetivaInvoice
    {
        return new EstimacionObjetivaInvoice(
            new VatRateResolver,
            new IrpfRetentionResolver,
        );
    }

    private function nif(): Nif
    {
        return new Nif('00000000T');
    }

    /**
     * Régimen simplificado IVA: la factura repercute IVA al cliente
     * pero el calculator añade un BreakdownLine INFO recordando que la
     * liquidación trimestral usa módulos.
     */
    public function test_simplificado_factura_a_particular_repercute_iva_pero_avisa_modulos(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Comida en bar',
                    quantity: '1',
                    unitPrice: new Money('100.00'),
                    vatRateType: VatRateType::REDUCED,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_SIMPLE'),
            issuerIrpfRegime: RegimeCode::fromString('EO'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::PARTICULAR,
        );

        $result = $this->calculator()->calculate($input);

        $this->assertSame('100.00', $result->subtotal->amount);
        $this->assertSame('10.00', $result->totalVat->amount);
        $this->assertSame('110.00', $result->totalToCharge->amount);

        $hasSimplifiedInfo = false;
        foreach ($result->breakdown->lines as $line) {
            if ($line->category === BreakdownCategory::INFO
                && stripos($line->concept, 'simplificado') !== false) {
                $hasSimplifiedInfo = true;
                break;
            }
        }
        $this->assertTrue($hasSimplifiedInfo, 'Falta nota INFO sobre régimen simplificado.');
    }

    /**
     * Actividad en módulos con retención al 1 % (transporte mercancías por
     * carretera, art. 95.6 RIRPF).
     */
    public function test_simplificado_con_actividad_modulos_retencion_1_porciento(): void
    {
        $activity = EconomicActivity::query()->create([
            'system' => 'cnae',
            'code' => '49410',
            'level' => 5,
            'name' => 'Transporte de mercancías por carretera',
            'year' => 2025,
        ]);
        ActivityRegimeMapping::query()->create([
            'activity_id' => $activity->id,
            'eligible_regimes' => ['EO', 'IVA_SIMPLE'],
            'irpf_retention_default' => '1.00',
        ]);

        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Transporte mercancía',
                    quantity: '1',
                    unitPrice: new Money('1000.00'),
                    vatRateType: VatRateType::GENERAL,
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_SIMPLE'),
            issuerIrpfRegime: RegimeCode::fromString('EO'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
            issuerActivityCode: '49410',
        );

        $result = $this->calculator()->calculate($input);

        // 1000 + 21 % - 1 % retención = 1000 + 210 - 10 = 1200.
        $this->assertSame('210.00', $result->totalVat->amount);
        $this->assertSame('10.00', $result->totalIrpfRetention->amount);
        $this->assertSame('1200.00', $result->totalToCharge->amount);
    }

    public function test_simplificado_a_empresa_sin_actividad_concreta_no_retiene(): void
    {
        $input = new InvoiceInput(
            lines: [
                new InvoiceLineInput(
                    description: 'Servicio',
                    quantity: '1',
                    unitPrice: new Money('500.00'),
                ),
            ],
            issuerNif: $this->nif(),
            issuerVatRegime: RegimeCode::fromString('IVA_SIMPLE'),
            issuerIrpfRegime: RegimeCode::fromString('EO'),
            issueDate: CarbonImmutable::create(2025, 6, 15),
            clientType: ClientType::EMPRESA,
        );

        $result = $this->calculator()->calculate($input);

        // EO sin actividad → retención 0.
        $this->assertSame('0.00', $result->totalIrpfRetention->amount);
        $this->assertSame('605.00', $result->totalToCharge->amount); // 500 + 105
    }
}
