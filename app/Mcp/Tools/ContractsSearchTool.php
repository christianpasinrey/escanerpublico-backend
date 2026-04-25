<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpContractResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Contract;

#[Description(
    'Busca contratos públicos PLACSP con filtros opcionales (organismo, NUTS, NIF empresa adjudicataria, full-text). '.
    'Devuelve los más recientes ordenados por fecha de actualización. '.
    'Cada item lleva detail_url al endpoint REST y public_url a la ficha humana.'
)]
class ContractsSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = Contract::query()
            ->select(['id', 'external_id', 'expediente', 'description_summary', 'organization_id', 'status_code', 'snapshot_updated_at'])
            ->when($request->input('search'), fn ($q, $term) => $q->where(function ($w) use ($term) {
                $w->where('description_summary', 'like', "%{$term}%")
                    ->orWhere('expediente', 'like', "%{$term}%");
            }))
            ->when($request->input('organization_id'), fn ($q, $id) => $q->where('organization_id', (int) $id))
            ->when($request->input('status_code'), fn ($q, $code) => $q->where('status_code', $code))
            ->orderByDesc('snapshot_updated_at')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpContractResource::collection($rows),
            source: 'PLACSP — Plataforma de Contratación del Sector Público',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Texto libre — busca en descripción y expediente.'),
            'organization_id' => $schema->integer()->description('ID interno del organismo contratante (ver organizations_search).'),
            'status_code' => $schema->string()->description('Código de estado: PUB (publicado), ADJ (adjudicado), RES (resuelto), ANUL (anulado).'),
            'limit' => $schema->integer()->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
