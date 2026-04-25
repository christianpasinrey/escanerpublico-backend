<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpCompanyResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Company;

#[Description(
    'Busca empresas adjudicatarias por nombre o NIF. Devuelve recuento de awards '.
    'y total adjudicado. Ordena por volumen total desc.'
)]
class CompaniesSearchTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = Company::query()
            ->select(['id', 'name', 'nif', 'identifier'])
            ->withCount('awards')
            ->withSum('awards as awards_sum_amount', 'amount')
            ->when($request->input('search'), fn (Builder $q, $term) => $q->where(function (Builder $w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('nif', 'like', "%{$term}%")
                    ->orWhere('identifier', 'like', "%{$term}%");
            }))
            ->when($request->input('nif'), fn (Builder $q, $nif) => $q->where('nif', $nif))
            ->orderByDesc('awards_sum_amount')
            ->limit($this->resolveLimit($request))
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpCompanyResource::collection($rows),
            source: 'PLACSP — adjudicatarios',
            note: 'Cifras agregadas de awards. Importes >= 1.000 M EUR pueden ser erratas (suspect_amount).',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Texto libre — busca en nombre, NIF e identifier.'),
            'nif' => $schema->string()
                ->description('NIF de la empresa (búsqueda exacta).'),
            'limit' => $schema->integer()
                ->description('Número máximo de resultados (1-50, por defecto 10).'),
        ];
    }

    private function resolveLimit(Request $request): int
    {
        return max(1, min(50, (int) ($request->input('limit') ?: 10)));
    }
}
