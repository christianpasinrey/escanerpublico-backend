<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpEconomicActivityResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Tax\Models\EconomicActivity;

#[Description(
    'Busca actividades económicas en el catálogo CNAE 2025 (sistema oficial de '.
    'clasificación de actividades) o IAE (Impuesto sobre Actividades Económicas). '.
    'Filtra por sistema, código padre o búsqueda full-text.'
)]
class TaxActivitiesSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = EconomicActivity::query()
            ->when($request->input('system'), fn (Builder $q, $s) => $q->where('system', $s))
            ->when($request->input('parent_code'), fn (Builder $q, $p) => $q->where('parent_code', $p))
            ->when($request->input('level'), fn (Builder $q, $l) => $q->where('level', (int) $l))
            ->when($request->input('search'), fn (Builder $q, $term) => $q->where(function (Builder $w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('system')
            ->orderBy('code')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpEconomicActivityResource::collection($rows),
            source: 'INE/AEAT — catálogos CNAE 2025 e IAE',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'system' => $schema->string()
                ->description('Sistema de clasificación: CNAE, IAE.'),
            'search' => $schema->string()
                ->description('Texto libre — busca en code y name.'),
            'parent_code' => $schema->string()
                ->description('Filtra hijos directos de un código padre (ej: "55" → todos los del 55.x).'),
            'level' => $schema->integer()
                ->description('Profundidad jerárquica (1 = sección, 2 = división, 3 = grupo, 4 = clase…).'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
