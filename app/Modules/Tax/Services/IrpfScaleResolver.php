<?php

namespace Modules\Tax\Services;

use Modules\Tax\Models\TaxBracket;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;

/**
 * Aplica una escala progresiva (estatal o autonómica) sobre una base liquidable
 * y devuelve la cuota íntegra correspondiente.
 *
 * Las escalas se almacenan en `tax_brackets` como tramos con campos:
 *   - from_amount: límite inferior (acumulado).
 *   - to_amount:   límite superior (acumulado, NULL = en adelante).
 *   - rate:        tipo aplicable a lo que excede de from_amount dentro del tramo.
 *   - fixed_amount: cuota acumulada hasta from_amount (se suma al tramo actual).
 *
 * Este formato es el oficial AEAT (cuota fija + tipo marginal).
 *
 * Fuente: Ley 35/2006 art. 63 (escala estatal) y boletines autonómicos
 * (Madrid, Cataluña, Andalucía, C.Valenciana) para la regional.
 */
class IrpfScaleResolver
{
    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    /**
     * Aplica la escala estatal IRPF (type=irpf_general, scope=state).
     */
    public function applyStateScale(FiscalYear $year, Money $base): Money
    {
        $brackets = $this->repository->getBrackets($year, 'irpf_general', 'state');

        return $this->applyBrackets($brackets->all(), $base);
    }

    /**
     * Aplica la escala autonómica IRPF (type=irpf_general, scope=regional).
     */
    public function applyRegionalScale(FiscalYear $year, RegionCode $region, Money $base): Money
    {
        if ($region->isState()) {
            throw new RuntimeException('Para la escala estatal usa applyStateScale().');
        }

        $brackets = $this->repository->getBrackets($year, 'irpf_general', 'regional', $region);

        return $this->applyBrackets($brackets->all(), $base);
    }

    /**
     * Aplica una colección de tramos sobre la base. Algoritmo:
     *   1. Encuentra el tramo cuyo from_amount <= base < to_amount (NULL=∞).
     *   2. cuota = fixed_amount + (base - from_amount) * (rate/100).
     *
     * @param  list<TaxBracket>|array<int, TaxBracket>  $brackets
     */
    public function applyBrackets(array $brackets, Money $base): Money
    {
        if ($brackets === []) {
            throw new RuntimeException('Escala IRPF vacía: no hay tramos sembrados.');
        }

        if ($base->isZero() || $base->isNegative()) {
            return Money::zero();
        }

        $sortedBrackets = $brackets;
        usort($sortedBrackets, static fn (TaxBracket $a, TaxBracket $b): int => bccomp((string) $a->from_amount, (string) $b->from_amount, 2)
        );

        foreach ($sortedBrackets as $bracket) {
            $from = (string) $bracket->from_amount;
            $to = $bracket->to_amount === null ? null : (string) $bracket->to_amount;

            $aboveFrom = bccomp($base->amount, $from, 2) >= 0;
            $belowTo = $to === null || bccomp($base->amount, $to, 2) < 0;

            if ($aboveFrom && $belowTo) {
                return $this->bracketQuota($bracket, $base);
            }
        }

        // Si la base es exactamente igual al to_amount del último, aplicamos el último.
        $last = end($sortedBrackets);
        if ($last !== false) {
            return $this->bracketQuota($last, $base);
        }

        return Money::zero();
    }

    private function bracketQuota(TaxBracket $bracket, Money $base): Money
    {
        $from = Money::fromFloat((float) $bracket->from_amount);
        $excess = $base->subtract($from);
        $rateDecimal = bcdiv((string) $bracket->rate, '100', 8);
        $variable = $excess->multiply($rateDecimal);
        $fixed = Money::fromFloat((float) ($bracket->fixed_amount ?? 0));

        return $fixed->add($variable);
    }
}
