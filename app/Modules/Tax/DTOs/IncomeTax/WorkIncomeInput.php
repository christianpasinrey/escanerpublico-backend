<?php

namespace Modules\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\Money;

/**
 * Rendimientos del trabajo (asalariado por cuenta ajena) para el modelo 100.
 *
 * Inmutable. Asume monoempleo (un único pagador). Los pluriempleos están
 * fuera del alcance del MVP — banner "próximamente".
 *
 * Campos:
 *  - gross: ingresos íntegros del trabajo (bruto anual percibido).
 *  - socialSecurityPaid: cotizaciones SS del trabajador efectivamente
 *    pagadas durante el ejercicio (descuento art. 19 LIRPF).
 *  - irpfWithheld: retenciones IRPF practicadas por la empresa (modelo 111
 *    trimestral; en la Renta se imputan como pago a cuenta).
 *
 * Fuente: Ley 35/2006 art. 17-20 (BOE-A-2006-20764).
 */
final readonly class WorkIncomeInput implements JsonSerializable
{
    public function __construct(
        public Money $gross,
        public Money $socialSecurityPaid,
        public Money $irpfWithheld,
    ) {
        if ($gross->isNegative()) {
            throw new InvalidArgumentException('Bruto del trabajo no puede ser negativo');
        }

        if ($socialSecurityPaid->isNegative()) {
            throw new InvalidArgumentException('socialSecurityPaid no puede ser negativo');
        }

        if ($irpfWithheld->isNegative()) {
            throw new InvalidArgumentException('irpfWithheld no puede ser negativo');
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'gross' => $this->gross,
            'social_security_paid' => $this->socialSecurityPaid,
            'irpf_withheld' => $this->irpfWithheld,
        ];
    }
}
