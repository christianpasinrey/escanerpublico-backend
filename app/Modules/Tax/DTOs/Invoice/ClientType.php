<?php

namespace Modules\Tax\DTOs\Invoice;

/**
 * Tipo del cliente al que se emite la factura. Determina si procede
 * retención IRPF al pagar al autónomo.
 *
 * - "empresa" (sociedad o autónomo cliente): tiene obligación de retener
 *   cuando el emisor es profesional o agricultor sujeto a retención.
 * - "particular" (consumidor final): no retiene IRPF nunca.
 *
 * Fuente: art. 99.2 Ley 35/2006 IRPF y art. 76 RD 439/2007 (Reglamento IRPF).
 */
enum ClientType: string
{
    case EMPRESA = 'empresa';
    case PARTICULAR = 'particular';

    public function label(): string
    {
        return match ($this) {
            self::EMPRESA => 'Empresa o autónomo',
            self::PARTICULAR => 'Consumidor final',
        };
    }

    public function withholdsIrpf(): bool
    {
        return $this === self::EMPRESA;
    }
}
