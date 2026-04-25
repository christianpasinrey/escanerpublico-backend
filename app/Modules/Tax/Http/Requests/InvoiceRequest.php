<?php

namespace Modules\Tax\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\Tax\DTOs\Invoice\ClientType;
use Modules\Tax\DTOs\Invoice\InvoiceInput;
use Modules\Tax\DTOs\Invoice\InvoiceLineInput;
use Modules\Tax\DTOs\Invoice\VatRateType;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\Nif;
use Modules\Tax\ValueObjects\RegimeCode;
use Throwable;

/**
 * FormRequest para POST /api/v1/tax/invoice.
 *
 * Valida tipos básicos en `rules()` y luego construye el InvoiceInput
 * (que aplica validaciones de dominio adicionales en sus constructores
 * — por ejemplo NIF con dígito de control, regímenes IVA/IRPF coherentes,
 * importes positivos, etc.).
 *
 * Si la construcción del DTO lanza una excepción de validación de dominio,
 * la convertimos en una respuesta 422 con el mensaje claro.
 */
class InvoiceRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'min:1', 'max:500'],
            'lines.*.quantity' => ['required'],
            'lines.*.unit_price' => ['required'],
            'lines.*.vat_rate_type' => ['required', 'string', 'in:'.implode(',', array_column(VatRateType::cases(), 'value'))],
            'lines.*.irpf_retention_applies' => ['nullable', 'boolean'],

            'issuer_nif' => ['required', 'string', 'min:9', 'max:12'],
            'issuer_vat_regime' => ['required', 'string'],
            'issuer_irpf_regime' => ['required', 'string'],
            'issuer_activity_code' => ['nullable', 'string', 'max:32'],
            'issuer_new_activity_flag' => ['nullable', 'boolean'],

            'client_nif' => ['nullable', 'string', 'min:9', 'max:12'],
            'client_type' => ['required', 'string', 'in:'.implode(',', array_column(ClientType::cases(), 'value'))],
            'client_country' => ['nullable', 'string', 'size:2'],

            'issue_date' => ['required', 'date'],
            'surcharge_equivalence_flag' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Debe incluir al menos una línea de factura.',
            'lines.min' => 'Debe incluir al menos una línea de factura.',
            'lines.*.description.required' => 'Cada línea debe tener una descripción.',
            'lines.*.quantity.required' => 'Cada línea debe tener una cantidad.',
            'lines.*.unit_price.required' => 'Cada línea debe tener un precio unitario.',
            'lines.*.vat_rate_type.in' => 'Tipo de IVA inválido (admitidos: general, reduced, super_reduced, special, zero, exempt).',
            'issuer_nif.required' => 'El NIF del emisor es obligatorio.',
            'issuer_vat_regime.required' => 'El régimen IVA del emisor es obligatorio.',
            'issuer_irpf_regime.required' => 'El régimen IRPF del emisor es obligatorio.',
            'client_type.in' => 'Tipo de cliente inválido (admitidos: empresa, particular).',
            'client_country.size' => 'El país debe ser un código ISO de 2 letras (ej: ES, FR).',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'issue_date.date' => 'La fecha de emisión debe ser una fecha válida.',
        ];
    }

    /**
     * Construye y devuelve el InvoiceInput a partir de los datos validados.
     * Las validaciones de dominio (NIF check digit, regímenes válidos, importes)
     * se ejecutan en los constructores de los Value Objects y DTOs.
     */
    public function toInvoiceInput(): InvoiceInput
    {
        try {
            $linesInput = [];
            /** @var array<int, array<string, mixed>> $rawLines */
            $rawLines = $this->validated('lines');
            foreach ($rawLines as $rawLine) {
                $unitPrice = $this->parseUnitPrice($rawLine['unit_price']);
                $quantity = (string) $rawLine['quantity'];
                $linesInput[] = new InvoiceLineInput(
                    description: (string) $rawLine['description'],
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    vatRateType: VatRateType::from((string) $rawLine['vat_rate_type']),
                    irpfRetentionApplies: (bool) ($rawLine['irpf_retention_applies'] ?? true),
                );
            }

            $clientNifRaw = $this->validated('client_nif');

            return new InvoiceInput(
                lines: $linesInput,
                issuerNif: Nif::fromString((string) $this->validated('issuer_nif')),
                issuerVatRegime: RegimeCode::fromString((string) $this->validated('issuer_vat_regime')),
                issuerIrpfRegime: RegimeCode::fromString((string) $this->validated('issuer_irpf_regime')),
                issueDate: CarbonImmutable::parse((string) $this->validated('issue_date')),
                clientType: ClientType::from((string) $this->validated('client_type')),
                issuerActivityCode: $this->validated('issuer_activity_code'),
                issuerNewActivityFlag: (bool) ($this->validated('issuer_new_activity_flag') ?? false),
                clientNif: $clientNifRaw !== null && $clientNifRaw !== ''
                    ? Nif::fromString((string) $clientNifRaw)
                    : null,
                clientCountry: strtoupper((string) ($this->validated('client_country') ?? 'ES')),
                surchargeEquivalenceFlag: (bool) ($this->validated('surcharge_equivalence_flag') ?? false),
            );
        } catch (Throwable $e) {
            // Domain validation error → 422 con mensaje útil.
            throw new HttpResponseException(response()->json([
                'message' => 'Datos de factura inválidos: '.$e->getMessage(),
                'errors' => [
                    'invoice' => [$e->getMessage()],
                ],
            ], 422));
        }
    }

    /**
     * Convierte unit_price (puede llegar como número, string o array
     * {amount,currency}) a Money.
     */
    private function parseUnitPrice(mixed $raw): Money
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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Datos de factura inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
