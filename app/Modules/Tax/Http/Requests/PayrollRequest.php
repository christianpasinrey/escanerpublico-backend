<?php

namespace Modules\Tax\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Tax\DTOs\Payroll\ContractType;
use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;

/**
 * Validación de la request de calculadora de nómina.
 *
 * Las CCAA aceptadas en MVP son MD, CT, AN, VC. Se valida también el rango
 * de año (FiscalYear::MIN_SUPPORTED → 2026 inclusive).
 */
class PayrollRequest extends FormRequest
{
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
            'gross_annual' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'payments_count' => ['required', 'integer', 'in:12,14'],
            'region' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_REGIONS)],
            'year' => ['required', 'integer', 'min:'.FiscalYear::MIN_SUPPORTED, 'max:2026'],
            'contract_type' => ['required', 'string', 'in:indefinido,temporal'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'disability_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'descendants' => ['integer', 'min:0', 'max:20'],
            'descendants_under_3' => ['integer', 'min:0', 'max:20', 'lte:descendants'],
            'ascendants_over_65_living' => ['integer', 'min:0', 'max:10'],
            'ascendants_disabled_living' => ['integer', 'min:0', 'max:10'],
            'married' => ['boolean'],
            'spouse_has_income' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'region.in' => 'Las CCAA cubiertas en el MVP son: MD (Madrid), CT (Cataluña), AN (Andalucía), VC (Comunidad Valenciana).',
            'descendants_under_3.lte' => 'El número de descendientes menores de 3 años no puede ser superior al número total de descendientes.',
            'payments_count.in' => 'El número de pagas debe ser 12 (sin extras prorrateadas) o 14 (con extras prorrateadas).',
        ];
    }

    public function toPayrollInput(): PayrollInput
    {
        $birthDate = $this->input('birth_date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('birth_date'))
            : null;

        // CarbonImmutable::createFromFormat puede devolver false en versiones antiguas.
        if ($birthDate === false) {
            $birthDate = null;
        }

        return new PayrollInput(
            grossAnnual: Money::fromFloat((float) $this->input('gross_annual')),
            paymentsCount: (int) $this->input('payments_count'),
            region: RegionCode::fromCode((string) $this->input('region')),
            year: FiscalYear::fromInt((int) $this->input('year')),
            contractType: ContractType::from((string) $this->input('contract_type')),
            birthDate: $birthDate,
            disabilityPercent: $this->input('disability_percent') !== null
                ? (int) $this->input('disability_percent')
                : null,
            descendants: (int) $this->input('descendants', 0),
            descendantsUnder3: (int) $this->input('descendants_under_3', 0),
            ascendantsOver65Living: (int) $this->input('ascendants_over_65_living', 0),
            ascendantsDisabledLiving: (int) $this->input('ascendants_disabled_living', 0),
            married: $this->boolean('married'),
            spouseHasIncome: $this->boolean('spouse_has_income', true),
        );
    }
}
