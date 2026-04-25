<?php

namespace Modules\Tax\Calculators\Invoice;

use InvalidArgumentException;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceResult;
use Modules\Tax\Services\Invoice\IrpfRetentionResolver;
use Modules\Tax\Services\Invoice\SurchargeEquivalenceResolver;
use Modules\Tax\Services\Invoice\VatRateResolver;

/**
 * Dispatcher de calculators de factura por régimen IVA.
 *
 * Selecciona la implementación específica según `issuerVatRegime`:
 *  - IVA_GEN, IVA_CAJA → EstimacionDirectaInvoice
 *  - IVA_SIMPLE → EstimacionObjetivaInvoice
 *  - IVA_RE → RecargoEquivalenciaInvoice
 *
 * Si llega un régimen IVA fuera del MVP (IVA_REAGP, IVA_REBU, IVA_OSS, etc.),
 * lanza InvalidArgumentException con un mensaje claro indicando que está
 * fuera de alcance del módulo M5 y dirige al usuario al backlog (M9+).
 */
class InvoiceCalculator
{
    public function __construct(
        private readonly VatRateResolver $vatResolver,
        private readonly IrpfRetentionResolver $irpfResolver,
        private readonly SurchargeEquivalenceResolver $surchargeResolver,
    ) {}

    public function calculate(InvoiceInput $input): InvoiceResult
    {
        return $this->resolveImplementation($input)->calculate($input);
    }

    private function resolveImplementation(InvoiceInput $input): InvoiceRegimeCalculator
    {
        return match ($input->issuerVatRegime->code) {
            'IVA_GEN', 'IVA_CAJA' => new EstimacionDirectaInvoice(
                $this->vatResolver,
                $this->irpfResolver,
                $this->surchargeResolver,
            ),
            'IVA_SIMPLE' => new EstimacionObjetivaInvoice(
                $this->vatResolver,
                $this->irpfResolver,
            ),
            'IVA_RE' => new RecargoEquivalenciaInvoice(
                $this->vatResolver,
                $this->irpfResolver,
            ),
            default => throw new InvalidArgumentException(
                "Régimen IVA '{$input->issuerVatRegime->code}' fuera del alcance del MVP. ".
                'Soportados en M5: IVA_GEN, IVA_CAJA, IVA_SIMPLE, IVA_RE.'
            ),
        };
    }
}
