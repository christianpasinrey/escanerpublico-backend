<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpSubsidyGrantResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Subsidies\Models\SubsidyGrant;

#[Description(
    'Busca concesiones individuales de subvenciones (BDNS). Filtra por NIF de '.
    'beneficiario, importe mínimo, organismo o convocatoria. Cifras >= 1.000 M EUR '.
    'pueden ser erratas y vienen marcadas con suspect_amount.'
)]
class SubsidiesGrantsSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = SubsidyGrant::query()
            ->select([
                'id', 'source', 'external_id', 'cod_concesion', 'call_id',
                'organization_id', 'company_id', 'beneficiario_nif', 'beneficiario_name',
                'grant_date', 'amount', 'ayuda_equivalente', 'instrumento',
                'tiene_proyecto', 'url_br',
            ])
            ->when($request->input('beneficiario_nif'), fn (Builder $q, $nif) => $q->where('beneficiario_nif', $nif))
            ->when($request->input('min_amount'), fn (Builder $q, $min) => $q->where('amount', '>=', (float) $min))
            ->when($request->input('organization_id'), fn (Builder $q, $id) => $q->where('organization_id', (int) $id))
            ->when($request->input('call_id'), fn (Builder $q, $id) => $q->where('call_id', (int) $id))
            ->orderByDesc('grant_date')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpSubsidyGrantResource::collection($rows),
            source: 'BDNS — concesiones individuales',
            note: 'Cifras >= 1.000 M EUR pueden ser erratas del feed (campo suspect_amount).',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'beneficiario_nif' => $schema->string()
                ->description('NIF del beneficiario (persona física o jurídica).'),
            'min_amount' => $schema->number()
                ->description('Importe mínimo concedido en EUR.'),
            'organization_id' => $schema->integer()
                ->description('ID interno del organismo concesor.'),
            'call_id' => $schema->integer()
                ->description('ID interno de la convocatoria.'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
