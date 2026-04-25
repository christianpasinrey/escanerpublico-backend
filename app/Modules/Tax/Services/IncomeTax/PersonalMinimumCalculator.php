<?php

namespace Modules\Tax\Services\IncomeTax;

use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Mínimo personal y familiar IRPF (Ley 35/2006 art. 56-61).
 *
 * Variante "anual / declaración Renta" del MinimumPersonalCalculator que existe
 * en M4 (esa funciona sobre PayrollInput; aquí trabajamos con TaxpayerSituation
 * extraído del IncomeTaxInput).
 *
 * Componentes:
 *  - Mínimo personal del contribuyente (5.550 €) + incremento por edad (>65, >75)
 *    art. 57 LIRPF.
 *  - Mínimo por descendiente: progresivo (1º 2.400, 2º 2.700, 3º 4.000, 4º+ 4.500)
 *    + incremento 2.800 € por hijo < 3 años. Art. 58 LIRPF.
 *  - Mínimo por ascendiente >65 a cargo: 1.150 € + 1.400 € adicional si > 75.
 *    Art. 59 LIRPF.
 *  - Mínimo por discapacidad del contribuyente: 3.000 € (<65 % grado),
 *    9.000 € (≥65 %). Art. 60 LIRPF.
 *
 * @see https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764
 */
class PersonalMinimumCalculator
{
    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    public function calculate(FiscalYear $year, TaxpayerSituation $situation): Money
    {
        $total = $this->personalMinimum($year, $situation);
        $total = $total->add($this->descendantsMinimum($year, $situation));
        $total = $total->add($this->ascendantsMinimum($year, $situation));
        $total = $total->add($this->disabilityMinimum($year, $situation));

        return $total;
    }

    private function personalMinimum(FiscalYear $year, TaxpayerSituation $situation): Money
    {
        $base = $this->paramAsMoney($year, 'irpf.minimo_personal_general');

        if ($situation->ageAtYearEnd !== null) {
            if ($situation->ageAtYearEnd >= 65) {
                $base = $base->add($this->paramAsMoney($year, 'irpf.minimo_personal_mayor_65'));
            }
            if ($situation->ageAtYearEnd >= 75) {
                $base = $base->add($this->paramAsMoney($year, 'irpf.minimo_personal_mayor_75'));
            }
        }

        return $base;
    }

    private function descendantsMinimum(FiscalYear $year, TaxpayerSituation $situation): Money
    {
        if ($situation->descendants <= 0) {
            return Money::zero();
        }

        $values = [
            1 => 'irpf.minimo_descendiente.primero',
            2 => 'irpf.minimo_descendiente.segundo',
            3 => 'irpf.minimo_descendiente.tercero',
        ];

        $total = Money::zero();
        for ($i = 1; $i <= $situation->descendants; $i++) {
            $key = $values[$i] ?? 'irpf.minimo_descendiente.cuarto_y_siguientes';
            $total = $total->add($this->paramAsMoney($year, $key));
        }

        if ($situation->descendantsUnder3 > 0) {
            $under3Increment = $this->paramAsMoney($year, 'irpf.minimo_descendiente.menor_3_anios');
            $total = $total->add($under3Increment->multiply((string) $situation->descendantsUnder3));
        }

        return $total;
    }

    private function ascendantsMinimum(FiscalYear $year, TaxpayerSituation $situation): Money
    {
        $total = Money::zero();

        if ($situation->ascendantsOver65Living > 0) {
            $perAscendant = $this->paramAsMoney($year, 'irpf.minimo_ascendiente_mayor_65');
            $total = $total->add($perAscendant->multiply((string) $situation->ascendantsOver65Living));
        }

        return $total;
    }

    private function disabilityMinimum(FiscalYear $year, TaxpayerSituation $situation): Money
    {
        if ($situation->disabilityPercent === null) {
            return Money::zero();
        }

        if ($situation->disabilityPercent >= 65) {
            return $this->paramAsMoney($year, 'irpf.minimo_discapacidad_grave');
        }

        if ($situation->disabilityPercent >= 33) {
            return $this->paramAsMoney($year, 'irpf.minimo_discapacidad_general');
        }

        return Money::zero();
    }

    private function paramAsMoney(FiscalYear $year, string $key): Money
    {
        $value = $this->repository->getParameter($year, $key, RegionCode::state());

        if ($value === null || is_array($value)) {
            return Money::zero();
        }

        return Money::fromFloat((float) $value);
    }
}
