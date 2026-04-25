<?php

namespace Modules\Tax\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Tax\DTOs\IncomeTax\EconomicActivityInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\DTOs\IncomeTax\WorkIncomeInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\RegionCode;
use Throwable;

/**
 * FormRequest para POST /api/v1/tax/income-tax.
 *
 * Valida tipos básicos en `rules()` y luego construye los DTOs específicos.
 * Las validaciones de dominio (regímenes válidos, ASALARIADO requiere workIncome,
 * EDN/EDS/EO requieren economicActivity, etc.) se ejecutan en los constructores
 * de los Value Objects y DTOs.
 *
 * En caso de error de dominio, se devuelve 422 con mensaje claro.
 */
class IncomeTaxRequest extends FormRequest
{
    public const SUPPORTED_REGIMES = ['EDN', 'EDS', 'EO', 'ASALARIADO_GEN'];

    public const SUPPORTED_REGIONS = ['MD', 'CT', 'AN', 'VC'];

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
            'regime' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_REGIMES)],
            'year' => ['required', 'integer', 'min:'.FiscalYear::MIN_SUPPORTED, 'max:2026'],
            'region' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_REGIONS)],

            'taxpayer_situation' => ['required', 'array'],
            'taxpayer_situation.married' => ['nullable', 'boolean'],
            'taxpayer_situation.spouse_has_income' => ['nullable', 'boolean'],
            'taxpayer_situation.descendants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'taxpayer_situation.descendants_under_3' => ['nullable', 'integer', 'min:0', 'max:20', 'lte:taxpayer_situation.descendants'],
            'taxpayer_situation.ascendants_over_65_living' => ['nullable', 'integer', 'min:0', 'max:10'],
            'taxpayer_situation.ascendants_disabled_living' => ['nullable', 'integer', 'min:0', 'max:10'],
            'taxpayer_situation.disability_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'taxpayer_situation.age_at_year_end' => ['nullable', 'integer', 'min:0', 'max:120'],

            'work_income' => ['nullable', 'array'],
            'work_income.gross' => ['required_with:work_income', 'numeric', 'min:0'],
            'work_income.social_security_paid' => ['required_with:work_income', 'numeric', 'min:0'],
            'work_income.irpf_withheld' => ['required_with:work_income', 'numeric', 'min:0'],

            'economic_activity' => ['nullable', 'array'],
            'economic_activity.activity_code' => ['required_with:economic_activity', 'string', 'max:32'],
            'economic_activity.gross_revenue' => ['required_with:economic_activity', 'numeric', 'min:0'],
            'economic_activity.deductible_expenses' => ['required_with:economic_activity', 'numeric', 'min:0'],
            'economic_activity.quarterly_payments_already_paid' => ['required_with:economic_activity', 'numeric', 'min:0'],
            'economic_activity.eo_modules_data' => ['nullable', 'array'],
            'economic_activity.eo_modules_data.*' => ['numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'regime.in' => 'Régimen IRPF fuera del alcance del MVP. Soportados: EDN, EDS, EO, ASALARIADO_GEN. Beckham y régimen foral están fuera de alcance.',
            'region.in' => 'Las CCAA cubiertas en el MVP son: MD (Madrid), CT (Cataluña), AN (Andalucía), VC (Comunidad Valenciana).',
            'year.min' => 'El año mínimo soportado es '.FiscalYear::MIN_SUPPORTED.'.',
            'year.max' => 'El año máximo soportado es 2026.',
            'taxpayer_situation.descendants_under_3.lte' => 'El número de descendientes menores de 3 años no puede ser superior al número total de descendientes.',
        ];
    }

    public function toIncomeTaxInput(): IncomeTaxInput
    {
        try {
            $regime = RegimeCode::fromString((string) $this->validated('regime'));
            $year = FiscalYear::fromInt((int) $this->validated('year'));
            $region = RegionCode::fromCode((string) $this->validated('region'));

            $taxpayer = $this->buildTaxpayerSituation();
            $workIncome = $this->buildWorkIncome();
            $economicActivity = $this->buildEconomicActivity();

            return new IncomeTaxInput(
                regime: $regime,
                year: $year,
                region: $region,
                taxpayerSituation: $taxpayer,
                workIncome: $workIncome,
                economicActivity: $economicActivity,
            );
        } catch (Throwable $e) {
            throw new HttpResponseException(response()->json([
                'message' => 'Datos de IRPF inválidos: '.$e->getMessage(),
                'errors' => [
                    'income_tax' => [$e->getMessage()],
                ],
            ], 422));
        }
    }

    private function buildTaxpayerSituation(): TaxpayerSituation
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated('taxpayer_situation', []);

        return new TaxpayerSituation(
            married: (bool) ($data['married'] ?? false),
            spouseHasIncome: (bool) ($data['spouse_has_income'] ?? true),
            descendants: (int) ($data['descendants'] ?? 0),
            descendantsUnder3: (int) ($data['descendants_under_3'] ?? 0),
            ascendantsOver65Living: (int) ($data['ascendants_over_65_living'] ?? 0),
            ascendantsDisabledLiving: (int) ($data['ascendants_disabled_living'] ?? 0),
            disabilityPercent: isset($data['disability_percent'])
                ? (int) $data['disability_percent']
                : null,
            ageAtYearEnd: isset($data['age_at_year_end'])
                ? (int) $data['age_at_year_end']
                : null,
        );
    }

    private function buildWorkIncome(): ?WorkIncomeInput
    {
        if (! $this->has('work_income')) {
            return null;
        }
        /** @var array<string, mixed>|null $data */
        $data = $this->validated('work_income');
        if ($data === null) {
            return null;
        }

        return new WorkIncomeInput(
            gross: Money::fromFloat((float) ($data['gross'] ?? 0)),
            socialSecurityPaid: Money::fromFloat((float) ($data['social_security_paid'] ?? 0)),
            irpfWithheld: Money::fromFloat((float) ($data['irpf_withheld'] ?? 0)),
        );
    }

    private function buildEconomicActivity(): ?EconomicActivityInput
    {
        if (! $this->has('economic_activity')) {
            return null;
        }
        /** @var array<string, mixed>|null $data */
        $data = $this->validated('economic_activity');
        if ($data === null) {
            return null;
        }

        $eoModules = $data['eo_modules_data'] ?? null;

        return new EconomicActivityInput(
            activityCode: (string) ($data['activity_code'] ?? ''),
            grossRevenue: Money::fromFloat((float) ($data['gross_revenue'] ?? 0)),
            deductibleExpenses: Money::fromFloat((float) ($data['deductible_expenses'] ?? 0)),
            quarterlyPaymentsAlreadyPaid: Money::fromFloat((float) ($data['quarterly_payments_already_paid'] ?? 0)),
            eoModulesData: is_array($eoModules) ? $eoModules : null,
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Datos de IRPF inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
