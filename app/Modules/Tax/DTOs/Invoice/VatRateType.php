<?php

namespace Modules\Tax\DTOs\Invoice;

/**
 * Tipos impositivos de IVA admitidos en una línea de factura.
 *
 * Mapea con la tabla `vat_product_rates.rate_type` cuando se quiere
 * resolver el tipo concreto desde catálogo, y permite también que el
 * cliente envíe directamente el tipo aplicable cuando ya lo conoce.
 *
 * Fuente: Ley 37/1992 (LIVA) — arts. 90, 91 y 91.dos.1.
 *   - General 21 %  → art. 90 LIVA
 *   - Reducido 10 % → art. 91.uno LIVA
 *   - Superreducido 4 % → art. 91.dos LIVA
 *   - Tipo especial 5 % → DT vigentes (medidas energía/alimentación 2022-2024)
 *   - Cero (0 %) → operaciones intracomunitarias / exportaciones
 *   - Exento → art. 20 LIVA (sanidad, enseñanza, alquiler vivienda…)
 */
enum VatRateType: string
{
    case GENERAL = 'general';
    case REDUCED = 'reduced';
    case SUPER_REDUCED = 'super_reduced';
    case SPECIAL = 'special';
    case ZERO = 'zero';
    case EXEMPT = 'exempt';

    /**
     * Tipo nominal por defecto si no se resuelve uno desde catálogo.
     * Útil para fallback cuando vat_product_rates no tiene fila concreta.
     */
    public function defaultRatePercentage(): string
    {
        return match ($this) {
            self::GENERAL => '21.00',
            self::REDUCED => '10.00',
            self::SUPER_REDUCED => '4.00',
            self::SPECIAL => '5.00',
            self::ZERO, self::EXEMPT => '0.00',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General (21 %)',
            self::REDUCED => 'Reducido (10 %)',
            self::SUPER_REDUCED => 'Superreducido (4 %)',
            self::SPECIAL => 'Especial (5 %)',
            self::ZERO => 'Cero (0 %)',
            self::EXEMPT => 'Exento',
        };
    }
}
