<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpTaxTypeResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Tax\Models\TaxType;

#[Description(
    'Lista los tipos impositivos del catálogo (IRPF, IVA, IS, IIEE, IBI…). '.
    'Filtra por scope, levy_type (impuesto/tasa/contribucion) y region_code para '.
    'tributos cedidos o autonómicos.'
)]
class TaxTypesListTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = TaxType::query()
            ->when($request->input('scope'), fn (Builder $q, $s) => $q->where('scope', $s))
            ->when($request->input('levy_type'), fn (Builder $q, $l) => $q->where('levy_type', $l))
            ->when($request->input('region_code'), fn (Builder $q, $r) => $q->where('region_code', $r))
            ->when($request->input('search'), fn (Builder $q, $term) => $q->where(function (Builder $w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('scope')
            ->orderBy('code')
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpTaxTypeResource::collection($rows),
            source: 'AEAT/CCAA/EELL — catálogo de tipos impositivos',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()
                ->description('Ámbito: state, regional, local.'),
            'levy_type' => $schema->string()
                ->description('Tipo de gravamen: impuesto, tasa, contribucion.'),
            'region_code' => $schema->string()
                ->description('Código ISO 3166-2:ES sin prefijo "ES-" (MD, CT, AN…).'),
            'search' => $schema->string()
                ->description('Texto libre — busca en code y name.'),
        ];
    }
}
