<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpLegislationNormResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Legislation\Models\LegislationNorm;

#[Description(
    'Busca disposiciones publicadas en el BOE (Sección I — disposiciones generales). '.
    'Filtra por sección/ámbito, rango (Ley, RD, Orden, Resolución), departamento '.
    'emisor y rango de fechas. Devuelve los más recientes ordenados por fecha de '.
    'publicación. La URL del BOE consolidado va en boe_url.'
)]
class LegislationSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = LegislationNorm::query()
            ->select([
                'id', 'source', 'external_id', 'ambito_code', 'ambito_text',
                'organization_id', 'departamento_code', 'departamento_text',
                'rango_code', 'rango_text', 'numero_oficial', 'titulo',
                'fecha_disposicion', 'fecha_publicacion', 'fecha_vigencia',
                'vigencia_agotada', 'estado_consolidacion_code',
                'estado_consolidacion_text', 'url_eli', 'url_html_consolidada',
            ])
            ->when($request->input('search'), function (Builder $q, $term) {
                $q->whereRaw('MATCH(titulo) AGAINST (? IN BOOLEAN MODE)', [$term]);
            })
            ->when($request->input('section'), fn (Builder $q, $s) => $q->where('ambito_code', $s))
            ->when($request->input('rango'), fn (Builder $q, $r) => $q->where('rango_code', $r))
            ->when($request->input('departamento'), fn (Builder $q, $d) => $q->where('departamento_text', 'like', "%{$d}%"))
            ->when($request->input('from_date'), fn (Builder $q, $d) => $q->whereDate('fecha_publicacion', '>=', $d))
            ->when($request->input('to_date'), fn (Builder $q, $d) => $q->whereDate('fecha_publicacion', '<=', $d))
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpLegislationNormResource::collection($rows),
            source: 'BOE — Boletín Oficial del Estado (Sección I)',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Búsqueda full-text en el título (boolean mode MySQL).'),
            'section' => $schema->string()
                ->description('Código de ámbito (ámbito territorial / sección BOE).'),
            'rango' => $schema->string()
                ->description('Código de rango: ley, ley-organica, real-decreto, real-decreto-ley, orden, resolucion, etc.'),
            'departamento' => $schema->string()
                ->description('Texto del departamento emisor (búsqueda por LIKE).'),
            'from_date' => $schema->string()
                ->description('Fecha mínima de publicación (YYYY-MM-DD).'),
            'to_date' => $schema->string()
                ->description('Fecha máxima de publicación (YYYY-MM-DD).'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
