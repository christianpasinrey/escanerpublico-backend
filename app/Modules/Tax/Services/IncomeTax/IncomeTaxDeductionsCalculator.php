<?php

namespace Modules\Tax\Services\IncomeTax;

use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Modules\Tax\ValueObjects\TaxRate;

/**
 * Calculadora de deducciones IRPF.
 *
 * Cobertura MVP:
 *  - Estatales: vivienda histórica (régimen transitorio art. 68.1 LIRPF para
 *    inmuebles adquiridos antes del 01/01/2013), donativos a entidades sin
 *    fines lucrativos (Ley 49/2002 art. 19: 80 % primeros 250 €, 40 % resto).
 *  - Autonómicas comunes para MD/CT/AN/VC: nacimiento/adopción, alquiler
 *    vivienda habitual, familia numerosa.
 *
 * NOTA MVP: las deducciones requieren campos de input adicionales (donativos
 * efectuados, importe alquiler pagado, etc.). En esta primera versión sólo
 * exponemos los métodos públicos para que puedan invocarse pasando importes;
 * el flujo del IncomeTaxCalculator no recoge aún todos los datos detallados,
 * por lo que las deducciones se aplican a 0 cuando no hay input específico.
 *
 * Esto deja la puerta abierta para una M6.1 que añada esos campos sin
 * romper backwards compatibility.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764 (LIRPF art. 68)
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2002-25039 (Ley 49/2002 art. 19)
 */
class IncomeTaxDeductionsCalculator
{
    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    /**
     * Deducción por donativos según Ley 49/2002 art. 19:
     *  - 80 % sobre los primeros 250 €.
     *  - 40 % sobre el exceso (45 % si donaciones recurrentes a la misma entidad
     *    en los 2 ejercicios anteriores; en MVP no manejamos recurrencia, usamos 40 %).
     *  - El resultado no puede superar el 10 % de la base liquidable.
     */
    public function donativosDeduction(Money $donatedAmount, Money $taxableBase): Money
    {
        if ($donatedAmount->isZero() || $donatedAmount->isNegative()) {
            return Money::zero();
        }

        $umbral = Money::fromFloat(250.00);
        $deduction = Money::zero();

        if ($donatedAmount->compare($umbral) <= 0) {
            // Hasta 250 € → 80 %.
            $deduction = $donatedAmount->applyRate(TaxRate::fromPercentage(80));
        } else {
            // Primeros 250 al 80 %, exceso al 40 %.
            $deduction = $umbral->applyRate(TaxRate::fromPercentage(80));
            $exceso = $donatedAmount->subtract($umbral);
            $deduction = $deduction->add($exceso->applyRate(TaxRate::fromPercentage(40)));
        }

        // Tope 10 % base liquidable.
        $tope = $taxableBase->applyRate(TaxRate::fromPercentage(10));
        if ($deduction->compare($tope) > 0) {
            $deduction = $tope;
        }

        return $deduction;
    }

    /**
     * Deducción por inversión en vivienda habitual (régimen transitorio
     * disposición transitoria 18ª LIRPF):
     *  - 15 % sobre los importes satisfechos durante el año (capital + intereses).
     *  - Tope: 9.040 €/año por declarante.
     *
     * Sólo aplica a viviendas adquiridas antes del 01/01/2013 (eliminado para
     * adquisiciones posteriores por Ley 16/2012).
     */
    public function viviendaHistoricaDeduction(Money $housingPaid, bool $eligible): Money
    {
        if (! $eligible || $housingPaid->isZero() || $housingPaid->isNegative()) {
            return Money::zero();
        }

        $tope = Money::fromFloat(9040.00);
        $base = $housingPaid->compare($tope) > 0 ? $tope : $housingPaid;

        return $base->applyRate(TaxRate::fromPercentage(15));
    }

    /**
     * Deducción autonómica por nacimiento o adopción.
     * Importe fijo según CCAA (parámetro irpf.deduccion_nacimiento_adopcion).
     */
    public function autonomicaNacimientoAdopcion(
        FiscalYear $year,
        RegionCode $region,
        TaxpayerSituation $situation,
    ): Money {
        if ($region->isState() || $situation->descendantsUnder3 <= 0) {
            return Money::zero();
        }

        $perChild = $this->paramAsMoney($year, $region, 'irpf.deduccion_nacimiento_adopcion');

        if ($perChild->isZero()) {
            return Money::zero();
        }

        return $perChild->multiply((string) $situation->descendantsUnder3);
    }

    /**
     * Deducción autonómica por familia numerosa (cuando hay 3+ descendientes).
     * Importe fijo según CCAA (parámetro irpf.deduccion_familia_numerosa).
     */
    public function autonomicaFamiliaNumerosa(
        FiscalYear $year,
        RegionCode $region,
        TaxpayerSituation $situation,
    ): Money {
        if ($region->isState() || $situation->descendants < 3) {
            return Money::zero();
        }

        return $this->paramAsMoney($year, $region, 'irpf.deduccion_familia_numerosa');
    }

    /**
     * Calcula el conjunto agregado de deducciones aplicables y devuelve una
     * lista de BreakdownLine + el total. Útil para que el calculator añada
     * todas las líneas de deducción al breakdown sin duplicar lógica.
     *
     * @return array{lines: list<BreakdownLine>, total: Money}
     */
    public function applyAll(
        FiscalYear $year,
        RegionCode $region,
        TaxpayerSituation $situation,
    ): array {
        $lines = [];
        $total = Money::zero();

        // Autonómicas comunes (nacimiento + familia numerosa).
        $nacimiento = $this->autonomicaNacimientoAdopcion($year, $region, $situation);
        if (! $nacimiento->isZero()) {
            $lines[] = new BreakdownLine(
                concept: "Deducción autonómica nacimiento/adopción ({$region->name()})",
                amount: $nacimiento,
                category: BreakdownCategory::DEDUCTION,
                legalReference: $this->autonomicaSourceUrl($region),
                explanation: 'Deducción autonómica por nacimiento o adopción de hijo menor de 3 años.',
            );
            $total = $total->add($nacimiento);
        }

        $familia = $this->autonomicaFamiliaNumerosa($year, $region, $situation);
        if (! $familia->isZero()) {
            $lines[] = new BreakdownLine(
                concept: "Deducción autonómica familia numerosa ({$region->name()})",
                amount: $familia,
                category: BreakdownCategory::DEDUCTION,
                legalReference: $this->autonomicaSourceUrl($region),
                explanation: 'Deducción autonómica por familia numerosa (3+ descendientes a cargo).',
            );
            $total = $total->add($familia);
        }

        return [
            'lines' => $lines,
            'total' => $total,
        ];
    }

    private function paramAsMoney(FiscalYear $year, RegionCode $region, string $key): Money
    {
        $value = $this->repository->getParameter($year, $key, $region);

        if ($value === null || is_array($value)) {
            return Money::zero();
        }

        return Money::fromFloat((float) $value);
    }

    private function autonomicaSourceUrl(RegionCode $region): string
    {
        return match ($region->code) {
            'MD' => 'https://www.bocm.es/',
            'CT' => 'https://dogc.gencat.cat/',
            'AN' => 'https://www.juntadeandalucia.es/boja/',
            'VC' => 'https://dogv.gva.es/',
            default => 'https://www.boe.es/',
        };
    }
}
