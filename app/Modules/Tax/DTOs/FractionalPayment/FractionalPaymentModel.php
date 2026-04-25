<?php

namespace Modules\Tax\DTOs\FractionalPayment;

/**
 * Modelos de pagos fraccionados IRPF cubiertos en M8.
 *
 *  - MODELO_130 — Estimación Directa (EDN/EDS).
 *  - MODELO_131 — Estimación Objetiva (EO/módulos).
 *
 * Modelo 202 (Sociedades) está fuera de alcance del MVP — corresponde
 * a sujetos pasivos del Impuesto sobre Sociedades, no a autónomos.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a105 (RIRPF arts. 105-110)
 * @see https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26173 (Orden HFP/3666/2024)
 */
enum FractionalPaymentModel: string
{
    case MODELO_130 = '130';
    case MODELO_131 = '131';

    public function label(): string
    {
        return match ($this) {
            self::MODELO_130 => 'Modelo 130 (Estimación Directa)',
            self::MODELO_131 => 'Modelo 131 (Estimación Objetiva)',
        };
    }

    /**
     * Códigos de régimen IRPF compatibles con cada modelo.
     *
     * @return list<string>
     */
    public function compatibleRegimes(): array
    {
        return match ($this) {
            self::MODELO_130 => ['EDN', 'EDS'],
            self::MODELO_131 => ['EO'],
        };
    }
}
