<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpTaxBracketResource;
use App\Mcp\Resources\McpTaxParameterResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Tax\Models\TaxBracket;
use Modules\Tax\Models\TaxParameter;

#[Description(
    'Obtiene los parámetros fiscales aplicables a un año (mínimo personal IRPF, '.
    'topes de cotización SS, cuotas autónomos, deducciones autonómicas, etc.). '.
    'Si la key matchea un tipo de escala progresiva (irpf_general, irpf_ahorro, '.
    'retencion, autonomo, is) también devuelve los tramos en el campo brackets.'
)]
class TaxParametersGetTool extends Tool
{
    public function handle(Request $request): Response
    {
        $year = (int) $request->input('year');
        $regionCode = $request->input('region_code');
        $key = $request->input('key');

        if ($year < 2023 || $year > 2100) {
            return Response::json([
                'count' => 0,
                'parameters' => [],
                'brackets' => [],
                'source' => 'AEAT',
                'license' => McpResponseEnvelope::LICENSE,
                'note' => "El parámetro year es obligatorio y debe estar entre 2023 y 2100. Recibido: {$year}.",
            ]);
        }

        $params = TaxParameter::query()
            ->where('year', $year)
            ->when($regionCode !== null && $regionCode !== '', fn (Builder $q) => $q->where('region_code', $regionCode))
            ->when($key !== null && $key !== '', fn (Builder $q) => $q->where('key', $key))
            ->orderBy('key')
            ->get();

        $brackets = collect();
        if ($key !== null && $key !== '') {
            $bracketTypes = ['irpf_general', 'irpf_ahorro', 'retencion', 'autonomo', 'is'];
            $matchedType = null;
            foreach ($bracketTypes as $t) {
                if (str_contains((string) $key, $t)) {
                    $matchedType = $t;
                    break;
                }
            }

            if ($matchedType !== null) {
                $brackets = TaxBracket::query()
                    ->where('year', $year)
                    ->where('type', $matchedType)
                    ->when($regionCode !== null && $regionCode !== '', fn (Builder $q) => $q->where('region_code', $regionCode))
                    ->orderBy('from_amount')
                    ->get();
            }
        }

        $paramsCollection = McpTaxParameterResource::collection($params);
        $bracketsCollection = McpTaxBracketResource::collection($brackets);

        return Response::json([
            'count' => $params->count(),
            'parameters' => $paramsCollection->resolve(),
            'brackets' => $bracketsCollection->resolve(),
            'source' => 'AEAT',
            'license' => McpResponseEnvelope::LICENSE,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()
                ->required()
                ->description('Ejercicio fiscal (>= 2023). Obligatorio.'),
            'region_code' => $schema->string()
                ->description('Código de CCAA (MD, CT, AN, VC…) si aplica un parámetro autonómico.'),
            'key' => $schema->string()
                ->description('Clave concreta (ej: irpf.minimo_personal, ss.tope_max_base). Si no se indica, devuelve todos los parámetros del año.'),
        ];
    }
}
