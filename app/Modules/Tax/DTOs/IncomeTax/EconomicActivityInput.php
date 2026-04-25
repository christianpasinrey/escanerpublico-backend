<?php

namespace Modules\Tax\DTOs\IncomeTax;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\Money;

/**
 * Datos de actividad económica (autónomo) para el modelo 100.
 *
 * Inmutable. Cubre EDN (Estimación Directa Normal), EDS (Simplificada) y
 * EO (Estimación Objetiva por módulos).
 *
 * Campos:
 *  - activityCode: código IAE/CNAE de la actividad principal (ej: '721.2' taxi).
 *  - grossRevenue: ingresos íntegros anuales (cifra de negocio).
 *  - deductibleExpenses: gastos íntegramente deducibles (sólo EDN/EDS).
 *    En EO no aplica — el rendimiento se calcula por módulos, no por gastos.
 *  - eoModulesData: datos de los signos/índices para EO. Estructura típica:
 *      [
 *        'm2_local' => 50,            // metros cuadrados del local
 *        'kw_potencia' => 8.5,        // potencia eléctrica contratada (kW)
 *        'personal_asalariado' => 1,  // unidades de personal asalariado
 *        'personal_no_asalariado' => 1, // unidades de personal no asalariado (titular)
 *        'vehiculo_propio' => 1,      // taxis/transporte (epígrafe 721.x)
 *        ...
 *      ]
 *    Sólo aplica si régimen=EO. Si null, EO usa valores por defecto a 0.
 *  - quarterlyPaymentsAlreadyPaid: suma de pagos fraccionados modelos 130/131
 *    ya ingresados durante el año (se restan de la cuota líquida en la Renta).
 *
 * Fuente: Ley 35/2006 art. 27-32 (rendimientos actividades económicas);
 * Orden HFP/1359/2023 (BOE-A-2023-25876) para módulos 2024;
 * Orden HFP/1397/2024 para 2025.
 */
final readonly class EconomicActivityInput implements JsonSerializable
{
    /**
     * @param  array<string,float|int>|null  $eoModulesData
     */
    public function __construct(
        public string $activityCode,
        public Money $grossRevenue,
        public Money $deductibleExpenses,
        public Money $quarterlyPaymentsAlreadyPaid,
        public ?array $eoModulesData = null,
    ) {
        if ($activityCode === '') {
            throw new InvalidArgumentException('activityCode obligatorio');
        }

        if ($grossRevenue->isNegative()) {
            throw new InvalidArgumentException('grossRevenue no puede ser negativo');
        }

        if ($deductibleExpenses->isNegative()) {
            throw new InvalidArgumentException('deductibleExpenses no puede ser negativo');
        }

        if ($quarterlyPaymentsAlreadyPaid->isNegative()) {
            throw new InvalidArgumentException(
                'quarterlyPaymentsAlreadyPaid no puede ser negativo',
            );
        }

        if ($eoModulesData !== null) {
            foreach ($eoModulesData as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('eoModulesData keys deben ser strings');
                }
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException(
                        "eoModulesData[{$key}] debe ser numérico",
                    );
                }
                if ($value < 0) {
                    throw new InvalidArgumentException(
                        "eoModulesData[{$key}] no puede ser negativo",
                    );
                }
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'activity_code' => $this->activityCode,
            'gross_revenue' => $this->grossRevenue,
            'deductible_expenses' => $this->deductibleExpenses,
            'eo_modules_data' => $this->eoModulesData,
            'quarterly_payments_already_paid' => $this->quarterlyPaymentsAlreadyPaid,
        ];
    }
}
