<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpSubsidyCallResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Subsidies\Models\SubsidyCall;

#[Description(
    'Busca convocatorias BDNS con filtros por nivel administrativo, mecanismo MRR y '.
    'búsqueda full-text en la descripción. Las descripciones largas vienen truncadas '.
    'a 240 chars — usa el detail_url para obtener el texto completo.'
)]
class SubsidiesCallsSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = SubsidyCall::query()
            ->select([
                'id', 'source', 'external_id', 'numero_convocatoria', 'organization_id',
                'description', 'reception_date', 'nivel1', 'nivel2', 'nivel3', 'is_mrr',
            ])
            ->when($request->input('search'), function (Builder $q, $term) {
                $q->whereRaw('MATCH(description) AGAINST (? IN BOOLEAN MODE)', [$term]);
            })
            ->when($request->input('nivel1'), fn (Builder $q, $n) => $q->where('nivel1', $n))
            ->when($request->input('is_mrr') !== null, function (Builder $q) use ($request) {
                $q->where('is_mrr', filter_var($request->input('is_mrr'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->input('organization_id'), fn (Builder $q, $id) => $q->where('organization_id', (int) $id))
            ->orderByDesc('reception_date')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpSubsidyCallResource::collection($rows),
            source: 'BDNS — Base de Datos Nacional de Subvenciones',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Búsqueda full-text en la descripción (boolean mode MySQL).'),
            'nivel1' => $schema->string()
                ->description('Nivel administrativo: ESTATAL, AUTONOMICA, LOCAL, OTROS.'),
            'is_mrr' => $schema->boolean()
                ->description('true = solo convocatorias del Mecanismo de Recuperación y Resiliencia.'),
            'organization_id' => $schema->integer()
                ->description('ID interno del organismo convocante.'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
