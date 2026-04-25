<?php

namespace Modules\Tax\DTOs\FractionalPayment;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;

/**
 * Input completo para calcular un pago fraccionado IRPF (modelos 130/131).
 *
 *  - model: '130' (Estimación Directa) o '131' (Estimación Objetiva).
 *  - regime: código de régimen IRPF — debe ser compatible con el modelo.
 *      Modelo 130 → EDN o EDS.
 *      Modelo 131 → EO.
 *  - year: ejercicio fiscal.
 *  - quarter: 1..4 (los pagos fraccionados son siempre trimestrales).
 *  - taxpayerSituation: situación familiar (descendientes para deducción
 *    art. 110.3.c LIRPF — 100 € por descendiente y trimestre).
 *
 * Para modelo 130 (EDN/EDS):
 *  - cumulativeGrossRevenue: ingresos íntegros acumulados desde 1-enero
 *    hasta el último día del trimestre actual.
 *  - cumulativeDeductibleExpenses: gastos deducibles acumulados (en EDS,
 *    sin contar la reducción genérica del 5/7 % — esta se calcula aparte).
 *  - withholdingsApplied: retenciones IRPF soportadas en facturas
 *    profesionales emitidas durante el año (las que minoran el pago
 *    fraccionado, art. 110.3.b LIRPF).
 *  - previousQuartersPayments: suma de pagos fraccionados ya ingresados en
 *    trimestres anteriores del mismo ejercicio (art. 110.3.a LIRPF).
 *
 * Para modelo 131 (EO):
 *  - activityCode: código IAE de la actividad (ej. '721.2' taxi).
 *  - eoModulesData: signos/índices de la actividad (estructura idéntica a M6).
 *  - salariedEmployees: nº personas asalariadas a 1-enero del ejercicio
 *    (determina el porcentaje aplicable: 4 % sin asalariados, 3 % con 1,
 *    2 % con ≥ 2). Art. 110.1.b RIRPF.
 *  - withholdingsApplied: retenciones soportadas (raras en EO pero existen,
 *    p.ej. transporte intermediado por plataformas).
 *  - previousQuartersPayments: pagos modelo 131 ya ingresados en trimestres
 *    anteriores del mismo ejercicio.
 *
 * Validaciones de dominio:
 *  - model debe ser compatible con regime (130 ↔ EDN/EDS; 131 ↔ EO).
 *  - quarter en rango 1..4.
 *  - Importes monetarios no negativos.
 *  - regime debe ser de scope IRPF.
 *
 * Disclaimer legal: cálculo informativo. La declaración real exige también
 * contemplar otros pagadores, atribución de rentas, comunidades de bienes,
 * etc. — todo fuera de alcance MVP.
 *
 * Fuente: Ley 35/2006 art. 99-101 (BOE-A-2006-20764);
 * RD 439/2007 RIRPF arts. 105-110 (BOE-A-2007-6820);
 * Orden HFP/3666/2024 modelos vigentes (BOE-A-2024-26173).
 */
final readonly class FractionalPaymentInput implements JsonSerializable
{
    /**
     * @param  array<string, float|int>|null  $eoModulesData
     */
    public function __construct(
        public FractionalPaymentModel $model,
        public RegimeCode $regime,
        public FiscalYear $year,
        public int $quarter,
        public TaxpayerSituation $taxpayerSituation,
        // Modelo 130
        public Money $cumulativeGrossRevenue = new Money('0.00'),
        public Money $cumulativeDeductibleExpenses = new Money('0.00'),
        // Modelo 131
        public ?string $activityCode = null,
        public ?array $eoModulesData = null,
        public int $salariedEmployees = 0,
        // Comunes
        public Money $withholdingsApplied = new Money('0.00'),
        public Money $previousQuartersPayments = new Money('0.00'),
    ) {
        if (! $regime->isIrpf()) {
            throw new InvalidArgumentException(
                "El régimen del pago fraccionado debe ser de scope 'irpf', recibido '{$regime->scope()}'.",
            );
        }

        if (! in_array($regime->code, $model->compatibleRegimes(), true)) {
            $compatible = implode(', ', $model->compatibleRegimes());
            throw new InvalidArgumentException(
                "El régimen '{$regime->code}' no es compatible con el modelo {$model->value}. ".
                "Regímenes compatibles con modelo {$model->value}: {$compatible}.",
            );
        }

        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Trimestre fuera de rango: {$quarter} (válidos: 1..4).");
        }

        if ($cumulativeGrossRevenue->isNegative()) {
            throw new InvalidArgumentException('cumulativeGrossRevenue no puede ser negativo.');
        }

        if ($cumulativeDeductibleExpenses->isNegative()) {
            throw new InvalidArgumentException('cumulativeDeductibleExpenses no puede ser negativo.');
        }

        if ($withholdingsApplied->isNegative()) {
            throw new InvalidArgumentException('withholdingsApplied no puede ser negativo.');
        }

        if ($previousQuartersPayments->isNegative()) {
            throw new InvalidArgumentException('previousQuartersPayments no puede ser negativo.');
        }

        if ($salariedEmployees < 0) {
            throw new InvalidArgumentException('salariedEmployees no puede ser negativo.');
        }

        if ($model === FractionalPaymentModel::MODELO_131) {
            if ($activityCode === null || $activityCode === '') {
                throw new InvalidArgumentException(
                    'El modelo 131 requiere activityCode (código IAE de la actividad EO).',
                );
            }
        }

        if ($eoModulesData !== null) {
            foreach ($eoModulesData as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('eoModulesData keys deben ser strings.');
                }
                if (! is_numeric($value)) {
                    throw new InvalidArgumentException(
                        "eoModulesData[{$key}] debe ser numérico.",
                    );
                }
                if ($value < 0) {
                    throw new InvalidArgumentException(
                        "eoModulesData[{$key}] no puede ser negativo.",
                    );
                }
            }
        }
    }

    public function quarterLabel(): string
    {
        return "{$this->quarter}T {$this->year->year}";
    }

    public function jsonSerialize(): array
    {
        return [
            'model' => $this->model->value,
            'regime' => $this->regime,
            'year' => $this->year->year,
            'quarter' => $this->quarter,
            'taxpayer_situation' => $this->taxpayerSituation,
            'cumulative_gross_revenue' => $this->cumulativeGrossRevenue,
            'cumulative_deductible_expenses' => $this->cumulativeDeductibleExpenses,
            'activity_code' => $this->activityCode,
            'eo_modules_data' => $this->eoModulesData,
            'salaried_employees' => $this->salariedEmployees,
            'withholdings_applied' => $this->withholdingsApplied,
            'previous_quarters_payments' => $this->previousQuartersPayments,
            'period_label' => $this->quarterLabel(),
        ];
    }
}
