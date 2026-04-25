<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpPayrollResultResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Tax\Calculators\Payroll\PayrollCalculator;
use Modules\Tax\DTOs\Payroll\ContractType;
use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;
use Throwable;

#[Description(
    'Calcula la nómina anual y mensual de un asalariado bajo Régimen General SS '.
    '+ tributación IRPF estatal+autonómica. Devuelve el desglose línea a línea '.
    '(cotizaciones, retención IRPF, deducciones, base/neto) con referencia legal '.
    'al BOE en cada concepto. CCAA soportadas en MVP: Madrid (MD), Cataluña (CT), '.
    'Andalucía (AN), Comunidad Valenciana (VC). País Vasco y Navarra (régimen foral) '.
    'están fuera de alcance. CALCULADORA INFORMATIVA, NO ASESORAMIENTO FISCAL.'
)]
class TaxPayrollCalculateTool extends Tool
{
    public function handle(Request $request): Response
    {
        $disclaimer = 'Calculadora informativa, no asesoramiento fiscal. '.
            'La nómina real depende del convenio colectivo, complementos, '.
            'antigüedad, dietas, horas extra y otros conceptos que esta '.
            'calculadora simplifica.';

        try {
            $grossAnnual = (float) $request->input('gross_annual');
            $paymentsCount = (int) $request->input('payments_count');
            $regionCode = strtoupper((string) ($request->input('region') ?? 'MD'));
            $year = (int) ($request->input('year') ?: (int) date('Y'));
            $contractTypeStr = (string) ($request->input('contract_type') ?: ContractType::Indefinido->value);

            if ($grossAnnual <= 0) {
                return Response::json(McpResponseEnvelope::empty(
                    source: 'AEAT — IRPF + Tesorería General de la Seguridad Social',
                    note: 'gross_annual debe ser positivo. '.$disclaimer,
                ));
            }

            $contractType = match ($contractTypeStr) {
                ContractType::Indefinido->value => ContractType::Indefinido,
                ContractType::Temporal->value => ContractType::Temporal,
                default => throw new InvalidArgumentException(
                    "contract_type inválido: {$contractTypeStr}. Valores: indefinido, temporal."
                ),
            };

            $input = new PayrollInput(
                grossAnnual: Money::fromFloat($grossAnnual),
                paymentsCount: $paymentsCount,
                region: RegionCode::fromCode($regionCode),
                year: FiscalYear::fromInt($year),
                contractType: $contractType,
                disabilityPercent: $this->intOrNull($request->input('disability_percent')),
                descendants: max(0, (int) ($request->input('descendants') ?? 0)),
                descendantsUnder3: max(0, (int) ($request->input('descendants_under_3') ?? 0)),
                ascendantsOver65Living: max(0, (int) ($request->input('ascendants_over_65') ?? 0)),
                ascendantsDisabledLiving: max(0, (int) ($request->input('ascendants_disabled') ?? 0)),
                married: filter_var($request->input('married'), FILTER_VALIDATE_BOOLEAN),
                spouseHasIncome: filter_var(
                    $request->input('spouse_has_income') ?? true,
                    FILTER_VALIDATE_BOOLEAN
                ),
            );

            /** @var PayrollCalculator $calculator */
            $calculator = app(PayrollCalculator::class);
            $result = $calculator->calculate($input);

            return Response::json(McpResponseEnvelope::single(
                McpPayrollResultResource::make($result),
                source: 'AEAT — IRPF + Tesorería General de la Seguridad Social',
                note: $disclaimer,
            ));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'AEAT — IRPF + Tesorería General de la Seguridad Social',
                note: $e->getMessage().' '.$disclaimer,
            ));
        } catch (Throwable $e) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'AEAT — IRPF + Tesorería General de la Seguridad Social',
                note: 'Error interno al calcular la nómina: '.$e->getMessage().' '.$disclaimer,
            ));
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'gross_annual' => $schema->number()
                ->required()
                ->description('Salario bruto anual en EUR (positivo).'),
            'payments_count' => $schema->integer()
                ->required()
                ->description('Número de pagas: 12 (sin extras) o 14 (con extras prorrateadas).'),
            'region' => $schema->string()
                ->required()
                ->description('Código CCAA (MD, CT, AN, VC). País Vasco/Navarra no soportadas en MVP.'),
            'year' => $schema->integer()
                ->required()
                ->description('Ejercicio fiscal (>= 2023).'),
            'contract_type' => $schema->string()
                ->description('Tipo de contrato: indefinido (default) | temporal.'),
            'descendants' => $schema->integer()
                ->description('Nº total de hijos a cargo (incluye los menores de 3).'),
            'descendants_under_3' => $schema->integer()
                ->description('Nº de descendientes menores de 3 años (incremento art. 58 LIRPF).'),
            'ascendants_over_65' => $schema->integer()
                ->description('Nº de ascendientes >65 que conviven a cargo.'),
            'ascendants_disabled' => $schema->integer()
                ->description('Nº de ascendientes con discapacidad ≥33% que conviven.'),
            'married' => $schema->boolean()
                ->description('true = convivencia matrimonial.'),
            'spouse_has_income' => $schema->boolean()
                ->description('true (default) = cónyuge con rentas anuales > 1.500 €.'),
            'disability_percent' => $schema->integer()
                ->description('Porcentaje de discapacidad reconocido (>=33). Omitir si no aplica.'),
        ];
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }
}
