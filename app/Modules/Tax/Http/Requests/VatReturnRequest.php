<?php

namespace Modules\Tax\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Tax\DTOs\VatReturn\VatReturnInput;
use Modules\Tax\DTOs\VatReturn\VatTransactionCategory;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use Modules\Tax\DTOs\VatReturn\VatTransactionInput;
use Modules\Tax\Models\VatProductRate;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Modules\Tax\ValueObjects\TaxRate;
use Throwable;

/**
 * FormRequest para POST /api/v1/tax/vat-return.
 *
 * Valida tipos básicos en `rules()` y luego construye VatReturnInput
 * (que aplica validaciones de dominio en sus constructores).
 *
 * Si la construcción del DTO lanza una excepción de dominio, devuelve
 * 422 con el mensaje claro.
 */
class VatReturnRequest extends FormRequest
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
            'regime' => ['required', 'string'],
            'year' => ['required', 'integer', 'min:'.FiscalYear::MIN_SUPPORTED, 'max:2100'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],

            'transactions' => ['required_unless:regime,IVA_SIMPLE', 'array', 'max:1000'],
            'transactions.*.direction' => ['required', 'string', 'in:'.implode(',', array_column(VatTransactionDirection::cases(), 'value'))],
            'transactions.*.date' => ['required', 'date'],
            'transactions.*.paid_date' => ['nullable', 'date'],
            'transactions.*.base' => ['required'],
            'transactions.*.vat_rate' => ['required'],
            'transactions.*.vat_amount' => ['required'],
            'transactions.*.surcharge_equivalence_amount' => ['nullable'],
            'transactions.*.category' => ['nullable', 'string', 'in:'.implode(',', array_column(VatTransactionCategory::cases(), 'value'))],
            'transactions.*.description' => ['required', 'string', 'min:1', 'max:500'],
            'transactions.*.client_or_supplier_nif' => ['nullable', 'string', 'min:9', 'max:12'],

            'previous_quota_carry_forward' => ['nullable'],
            'simplified_modules_data' => ['nullable', 'array'],
            'simplified_modules_data.modules' => ['nullable', 'array'],
            'simplified_modules_data.period_fraction' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'regime.required' => 'El régimen IVA es obligatorio.',
            'year.required' => 'El año fiscal es obligatorio.',
            'year.min' => 'El año fiscal mínimo soportado es '.FiscalYear::MIN_SUPPORTED.'.',
            'quarter.min' => 'El trimestre debe estar entre 1 y 4.',
            'quarter.max' => 'El trimestre debe estar entre 1 y 4.',
            'transactions.required_unless' => 'Debe incluir al menos una transacción (excepto en régimen simplificado).',
            'transactions.*.direction.in' => 'La dirección debe ser outgoing o incoming.',
            'transactions.*.date.required' => 'Cada transacción debe tener fecha.',
            'transactions.*.description.required' => 'Cada transacción debe tener descripción.',
            'transactions.*.category.in' => 'Categoría inválida (admitidas: domestic, intracom, imports, exports).',
        ];
    }

    public function toVatReturnInput(): VatReturnInput
    {
        try {
            $regime = RegimeCode::fromString((string) $this->validated('regime'));
            $year = FiscalYear::fromInt((int) $this->validated('year'));
            $quarter = $this->validated('quarter');
            $quarter = $quarter === null ? null : (int) $quarter;

            /** @var array<int, array<string, mixed>> $rawTx */
            $rawTx = $this->validated('transactions') ?? [];
            $transactions = [];
            foreach ($rawTx as $idx => $row) {
                $vatRate = TaxRate::fromPercentage((string) $row['vat_rate']);

                // Validación catálogo: el tipo IVA debe existir en
                // vat_product_rates para el año (o coincidir con un tipo
                // nominal por defecto 21/10/4/5/0).
                $this->assertVatRateInCatalog($vatRate, $year);

                $base = $this->parseMoney($row['base']);
                $vatAmount = $this->parseMoney($row['vat_amount']);
                $surcharge = isset($row['surcharge_equivalence_amount']) && $row['surcharge_equivalence_amount'] !== null
                    ? $this->parseMoney($row['surcharge_equivalence_amount'])
                    : null;

                $clientNifRaw = $row['client_or_supplier_nif'] ?? null;

                $transactions[] = new VatTransactionInput(
                    direction: VatTransactionDirection::from((string) $row['direction']),
                    date: CarbonImmutable::parse((string) $row['date']),
                    base: $base,
                    vatRate: $vatRate,
                    vatAmount: $vatAmount,
                    description: (string) $row['description'],
                    category: isset($row['category'])
                        ? VatTransactionCategory::from((string) $row['category'])
                        : VatTransactionCategory::DOMESTIC,
                    paidDate: isset($row['paid_date']) && $row['paid_date'] !== null && $row['paid_date'] !== ''
                        ? CarbonImmutable::parse((string) $row['paid_date'])
                        : null,
                    surchargeEquivalenceAmount: $surcharge,
                    clientOrSupplierNif: $clientNifRaw !== null && $clientNifRaw !== ''
                        ? Nif::fromString((string) $clientNifRaw)
                        : null,
                );
                unset($idx);
            }

            $rawCarry = $this->validated('previous_quota_carry_forward');
            $carry = $rawCarry === null
                ? new Money('0.00')
                : $this->parseMoney($rawCarry);

            return new VatReturnInput(
                regime: $regime,
                year: $year,
                transactions: $transactions,
                quarter: $quarter,
                previousQuotaCarryForward: $carry,
                simplifiedModulesData: $this->validated('simplified_modules_data'),
            );
        } catch (Throwable $e) {
            throw new HttpResponseException(response()->json([
                'message' => 'Datos de autoliquidación IVA inválidos: '.$e->getMessage(),
                'errors' => [
                    'vat_return' => [$e->getMessage()],
                ],
            ], 422));
        }
    }

    private function parseMoney(mixed $raw): Money
    {
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
     * Verifica que el porcentaje IVA introducido o bien existe en
     * vat_product_rates para el año, o bien coincide con un tipo nominal
     * por defecto (21, 10, 5, 4, 0). Si no, 422.
     */
    private function assertVatRateInCatalog(TaxRate $rate, FiscalYear $year): void
    {
        $allowedNominal = ['21.0000', '10.0000', '5.0000', '4.0000', '0.0000'];
        if (in_array($rate->percentage, $allowedNominal, true)) {
            return;
        }

        $exists = VatProductRate::query()
            ->where('year', $year->year)
            ->whereRaw('CAST(rate AS DECIMAL(7,4)) = CAST(? AS DECIMAL(7,4))', [$rate->percentage])
            ->exists();

        if (! $exists) {
            throw new \InvalidArgumentException(
                "Tipo IVA {$rate->percentage}% no encontrado en vat_product_rates para el año {$year->year} y no coincide con ningún tipo nominal (21, 10, 5, 4, 0)."
            );
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Datos de autoliquidación IVA inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
