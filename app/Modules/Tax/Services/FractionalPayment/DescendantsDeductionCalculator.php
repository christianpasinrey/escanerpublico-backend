<?php

namespace Modules\Tax\Services\FractionalPayment;

use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\ValueObjects\Money;

/**
 * Calculador de la deducción por descendientes en pagos fraccionados.
 *
 * Conforme al art. 110.3.c LIRPF y art. 110.3.c RIRPF, en cada pago
 * fraccionado (modelos 130 y 131) el contribuyente puede deducir
 * 100,00 € por cada descendiente que dé derecho a la aplicación del
 * mínimo por descendientes y conviva con él.
 *
 * IMPORTANTE — Esta deducción es trimestral (no anual): se aplica EN
 * CADA pago fraccionado del año, los 4 trimestres. NO se acumula ni
 * proporcionaliza por trimestre.
 *
 * Por simplicidad MVP, contamos `taxpayerSituation->descendants` como
 * proxy del nº de descendientes que dan derecho al mínimo (en la práctica
 * AEAT exige convivencia y rentas < 8.000 €/año del descendiente, pero
 * el dato granular no se modela en el TaxpayerSituation actual).
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a110 (art. 110 LIRPF)
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820#a110 (art. 110 RIRPF)
 */
class DescendantsDeductionCalculator
{
    /**
     * Importe fijo por descendiente y trimestre (art. 110.3.c LIRPF).
     */
    public const PER_DESCENDANT_PER_QUARTER = 100.00;

    public function calculate(TaxpayerSituation $situation): Money
    {
        $count = max(0, $situation->descendants);
        $amount = $count * self::PER_DESCENDANT_PER_QUARTER;

        return Money::fromFloat($amount);
    }

    public function legalReference(): string
    {
        return 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764#a110';
    }

    public function explanation(int $descendants): string
    {
        if ($descendants <= 0) {
            return 'Sin descendientes declarados con derecho al mínimo: deducción 0,00 €.';
        }

        $total = number_format($descendants * self::PER_DESCENDANT_PER_QUARTER, 2, ',', '.');

        return "Deducción por descendientes en pagos fraccionados (art. 110.3.c LIRPF): {$descendants} × 100,00 €/trimestre = {$total} €.";
    }
}
