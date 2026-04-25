<?php

namespace Modules\Tax\Services;

use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Calcula el mínimo personal y familiar IRPF (Ley 35/2006 art. 56-61).
 *
 * Se aplica como reducción de la base liquidable: la cuota se calcula
 * progresivamente sobre la base completa y sobre el mínimo, y se resta el
 * resultado del segundo. En esta clase devolvemos sólo el importe agregado
 * del mínimo personal+familiar; la cuota la calcula el IrpfScaleResolver.
 *
 * Componentes:
 *  - Mínimo personal del contribuyente (5.550 €) + incremento por edad (>65, >75)
 *    art. 57 LIRPF.
 *  - Mínimo por descendiente: progresivo (1º 2.400, 2º 2.700, 3º 4.000, 4º+ 4.500)
 *    + incremento 2.800 € por hijo < 3 años. Art. 58 LIRPF.
 *    Se computa el 50 % cuando hay convivencia con ambos progenitores
 *    (asumimos custodia compartida estándar para simplificar el MVP). Aquí asumimos
 *    monoempleo: contribuyente único que aplica el 100 %.
 *  - Mínimo por ascendiente >65 a cargo: 1.150 € + 1.400 € adicional si > 75.
 *    Art. 59 LIRPF.
 *  - Mínimo por discapacidad del contribuyente: 3.000 € (<65 % grado),
 *    9.000 € (≥65 %), + 3.000 € si necesita asistencia. Art. 60 LIRPF.
 *
 * Fuente: Ley 35/2006 art. 56-61 — https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764
 */
class MinimumPersonalCalculator
{
    public function __construct(
        private readonly TaxParameterRepository $repository,
    ) {}

    /**
     * Devuelve el mínimo personal+familiar aplicable a la situación dada.
     */
    public function calculate(PayrollInput $input): Money
    {
        $total = $this->personalMinimum($input);
        $total = $total->add($this->descendantsMinimum($input));
        $total = $total->add($this->ascendantsMinimum($input));
        $total = $total->add($this->disabilityMinimum($input));

        return $total;
    }

    private function personalMinimum(PayrollInput $input): Money
    {
        $base = $this->paramAsMoney('irpf.minimo_personal_general', $input);

        $age = $input->ageAtFiscalYearEnd();
        if ($age !== null) {
            if ($age >= 65) {
                $base = $base->add($this->paramAsMoney('irpf.minimo_personal_mayor_65', $input));
            }
            if ($age >= 75) {
                $base = $base->add($this->paramAsMoney('irpf.minimo_personal_mayor_75', $input));
            }
        }

        return $base;
    }

    private function descendantsMinimum(PayrollInput $input): Money
    {
        if ($input->descendants <= 0) {
            return Money::zero();
        }

        $values = [
            1 => 'irpf.minimo_descendiente.primero',
            2 => 'irpf.minimo_descendiente.segundo',
            3 => 'irpf.minimo_descendiente.tercero',
        ];

        $total = Money::zero();
        for ($i = 1; $i <= $input->descendants; $i++) {
            $key = $values[$i] ?? 'irpf.minimo_descendiente.cuarto_y_siguientes';
            $total = $total->add($this->paramAsMoney($key, $input));
        }

        if ($input->descendantsUnder3 > 0) {
            $under3Increment = $this->paramAsMoney('irpf.minimo_descendiente.menor_3_anios', $input);
            $total = $total->add($under3Increment->multiply((string) $input->descendantsUnder3));
        }

        return $total;
    }

    private function ascendantsMinimum(PayrollInput $input): Money
    {
        $total = Money::zero();

        if ($input->ascendantsOver65Living > 0) {
            $perAscendant = $this->paramAsMoney('irpf.minimo_ascendiente_mayor_65', $input);
            $total = $total->add($perAscendant->multiply((string) $input->ascendantsOver65Living));
        }

        return $total;
    }

    private function disabilityMinimum(PayrollInput $input): Money
    {
        if ($input->disabilityPercent === null) {
            return Money::zero();
        }

        if ($input->disabilityPercent >= 65) {
            return $this->paramAsMoney('irpf.minimo_discapacidad_grave', $input);
        }

        if ($input->disabilityPercent >= 33) {
            return $this->paramAsMoney('irpf.minimo_discapacidad_general', $input);
        }

        return Money::zero();
    }

    private function paramAsMoney(string $key, PayrollInput $input): Money
    {
        $value = $this->repository->getParameter($input->year, $key, RegionCode::state());

        if ($value === null) {
            return Money::zero();
        }

        if (is_array($value)) {
            // Si por configuración llega un objeto, no podemos sumarlo aquí: 0.
            return Money::zero();
        }

        return Money::fromFloat((float) $value);
    }
}
