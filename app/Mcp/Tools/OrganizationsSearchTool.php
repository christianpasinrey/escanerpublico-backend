<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpOrganizationResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Organization;

#[Description(
    'Busca organismos contratantes (DIR3) por nombre o NIF. '.
    'Incluye recuento de contratos y volumen total contratado por cada organismo. '.
    'Útil como primer paso para localizar al órgano antes de navegar a sus contratos.'
)]
class OrganizationsSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = Organization::query()
            ->select(['id', 'name', 'identifier', 'nif', 'type_code', 'parent_name'])
            ->withCount('contracts')
            ->withSum('contracts as total_amount', 'importe_con_iva')
            ->when($request->input('search'), fn (Builder $q, $term) => $q->where(function (Builder $w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('identifier', 'like', "%{$term}%")
                    ->orWhere('nif', 'like', "%{$term}%");
            }))
            ->when($request->input('nif'), fn (Builder $q, $nif) => $q->where('nif', $nif))
            ->orderByDesc('contracts_count')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpOrganizationResource::collection($rows),
            source: 'DIR3 — Directorio Común de Unidades Orgánicas y Oficinas',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Texto libre — busca en nombre, identificador DIR3 y NIF.'),
            'nif' => $schema->string()
                ->description('NIF del organismo (búsqueda exacta).'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
