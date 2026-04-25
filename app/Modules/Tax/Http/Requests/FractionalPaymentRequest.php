<?php

namespace Modules\Tax\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Throwable;

/**
 * FormRequest para POST /api/v1/tax/fractional-payment (modelos 130/131).
 *
 * Valida tipos básicos en `rules()` y construye el FractionalPaymentInput
 * (que aplica validaciones de dominio en su constructor). Si falla, 422.
 *
 * Disclaimer: cálculo informativo, no asesoramiento fiscal.
 */
class FractionalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'in:130,131'],
            'regime' => ['required', 'string'],
            'year' => ['required', 'integer', 'min:'.FiscalYear::MIN_SUPPORTED, 'max:2100'],
            'quarter' => ['required', 'integer', 'min:1', 'max:4'],

            'taxpayer_situation' => ['nullable', 'array'],
            'taxpayer_situation.descendants' => ['nullable', 'integer', 'min:0', 'max:30'],
            'taxpayer_situation.descendants_under_3' => ['nullable', 'integer', 'min:0', 'max:30'],

            // Modelo 130
            'cumulative_gross_revenue' => ['nullable'],
            'cumulative_deductible_expenses' => ['nullable'],

            // Modelo 131
            'activity_code' => ['nullable', 'string', 'max:20'],
            'eo_modules_data' => ['nullable', 'array'],
            'salaried_employees' => ['nullable', 'integer', 'min:0', 'max:1000'],

            // Comunes
            'withholdings_applied' => ['nullable'],
            'previous_quarters_payments' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model.required' => 'El modelo (130 o 131) es obligatorio.',
            'model.in' => 'El modelo debe ser 130 (Estimación Directa) o 131 (Estimación Objetiva).',
            'regime.required' => 'El régimen IRPF es obligatorio.',
            'year.required' => 'El año fiscal es obligatorio.',
            'year.min' => 'El año fiscal mínimo soportado es '.FiscalYear::MIN_SUPPORTED.'.',
            'quarter.required' => 'El trimestre es obligatorio (1..4).',
            'quarter.min' => 'El trimestre debe estar entre 1 y 4.',
            'quarter.max' => 'El trimestre debe estar entre 1 y 4.',
        ];
    }

    public function toFractionalPaymentInput(): FractionalPaymentInput
    {
        try {
            $model = FractionalPaymentModel::from((string) $this->validated('model'));
            $regime = RegimeCode::fromString((string) $this->validated('regime'));
            $year = FiscalYear::fromInt((int) $this->validated('year'));
            $quarter = (int) $this->validated('quarter');

            $situation = $this->buildTaxpayerSituation();

            $cumulativeGross = $this->parseMoneyOrZero($this->validated('cumulative_gross_revenue'));
            $cumulativeExpenses = $this->parseMoneyOrZero($this->validated('cumulative_deductible_expenses'));
            $withholdings = $this->parseMoneyOrZero($this->validated('withholdings_applied'));
            $previous = $this->parseMoneyOrZero($this->validated('previous_quarters_payments'));

            $activityCode = $this->validated('activity_code');
            $activityCode = $activityCode === null || $activityCode === '' ? null : (string) $activityCode;
            $eoModulesRaw = $this->validated('eo_modules_data');
            $eoModulesData = $this->normalizeEoModulesData($eoModulesRaw);
            $salariedEmployees = $this->validated('salaried_employees');
            $salariedEmployees = $salariedEmployees === null ? 0 : (int) $salariedEmployees;

            return new FractionalPaymentInput(
                model: $model,
                regime: $regime,
                year: $year,
                quarter: $quarter,
                taxpayerSituation: $situation,
                cumulativeGrossRevenue: $cumulativeGross,
                cumulativeDeductibleExpenses: $cumulativeExpenses,
                activityCode: $activityCode,
                eoModulesData: $eoModulesData,
                salariedEmployees: $salariedEmployees,
                withholdingsApplied: $withholdings,
                previousQuartersPayments: $previous,
            );
        } catch (Throwable $e) {
            throw new HttpResponseException(response()->json([
                'message' => 'Datos del pago fraccionado inválidos: '.$e->getMessage(),
                'errors' => [
                    'fractional_payment' => [$e->getMessage()],
                ],
            ], 422));
        }
    }

    private function buildTaxpayerSituation(): TaxpayerSituation
    {
        $raw = $this->validated('taxpayer_situation') ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }

        return new TaxpayerSituation(
            married: (bool) ($raw['married'] ?? false),
            spouseHasIncome: (bool) ($raw['spouse_has_income'] ?? true),
            descendants: (int) ($raw['descendants'] ?? 0),
            descendantsUnder3: (int) ($raw['descendants_under_3'] ?? 0),
            ascendantsOver65Living: (int) ($raw['ascendants_over_65_living'] ?? 0),
            ascendantsDisabledLiving: (int) ($raw['ascendants_disabled_living'] ?? 0),
            disabilityPercent: isset($raw['disability_percent']) && $raw['disability_percent'] !== null
                ? (int) $raw['disability_percent']
                : null,
            ageAtYearEnd: isset($raw['age_at_year_end']) && $raw['age_at_year_end'] !== null
                ? (int) $raw['age_at_year_end']
                : null,
        );
    }

    private function parseMoneyOrZero(mixed $raw): Money
    {
        if ($raw === null || $raw === '') {
            return new Money('0.00');
        }

        if (is_array($raw)) {
            $amount = (string) ($raw['amount'] ?? '0.00');
            $currency = (string) ($raw['currency'] ?? 'EUR');

            return new Money($amount, $currency);
        }

        if (is_numeric($raw)) {
            return Money::fromFloat((float) $raw);
        }

        return new Money((string) $raw);
    }

    /**
     * Normaliza eo_modules_data a array<string, float|int>|null.
     *
     * @return array<string, float|int>|null
     */
    private function normalizeEoModulesData(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (! is_numeric($value)) {
                continue;
            }
            $out[$key] = is_float($value) ? $value : (int) $value;
        }

        return $out === [] ? null : $out;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Datos del pago fraccionado inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
