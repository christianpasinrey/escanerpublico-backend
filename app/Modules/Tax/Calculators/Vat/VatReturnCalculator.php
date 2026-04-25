<?php

namespace Modules\Tax\Calculators\Vat;

use InvalidArgumentException;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatReturnResult;
use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\Services\Vat\VatPeriodResolver;
use Modules\Tax\Services\Vat\VatRegimeValidator;

/**
 * Dispatcher de calculators de autoliquidación de IVA (modelo 303/390)
 * por régimen.
 *
 *  - IVA_GEN  → RegimenGeneralVat
 *  - IVA_CAJA → CriterioCajaVat
 *  - IVA_SIMPLE → RegimenSimplificadoVat
 *
 * Si llega un régimen IVA fuera del MVP (REBU, REAGP, IVA_AAVV, IVA_OSS,
 * IVA_ISP, IVA_OIC, IVA_RE, IVA_EXENTO), lanza InvalidArgumentException
 * con mensaje claro indicando que está fuera de alcance del módulo M7.
 */
class VatReturnCalculator
{
    public function __construct(
        private readonly VatRegimeValidator $validator,
        private readonly VatPeriodResolver $periodResolver,
        private readonly Modelo303CasillasMapper $casillasMapper,
    ) {}

    public function calculate(VatReturnInput $input): VatReturnResult
    {
        $this->validator->validate($input);

        return $this->resolveImplementation($input)->calculate($input);
    }

    private function resolveImplementation(VatReturnInput $input): VatRegimeReturnCalculator
    {
        return match ($input->regime->code) {
            'IVA_GEN' => new RegimenGeneralVat(
                $this->periodResolver,
                $this->casillasMapper,
            ),
            'IVA_CAJA' => new CriterioCajaVat(
                $this->periodResolver,
                $this->casillasMapper,
            ),
            'IVA_SIMPLE' => new RegimenSimplificadoVat(
                $this->periodResolver,
                $this->casillasMapper,
            ),
            default => throw new InvalidArgumentException(
                "Régimen IVA '{$input->regime->code}' fuera del alcance del MVP M7. ".
                'Soportados: IVA_GEN, IVA_CAJA, IVA_SIMPLE. '.
                'Próximamente: REBU, REAGP, IVA_AAVV, IVA_OSS.'
            ),
        };
    }
}
