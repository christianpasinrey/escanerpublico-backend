<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpTaxRegimeResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Tax\Models\TaxRegime;

#[Description(
    'Lista los regímenes tributarios del catálogo (IRPF, IVA, IS, Seguridad Social). '.
    'Filtrable por scope (state, regional, local). Útil para descubrir qué obligaciones '.
    'aplica a un perfil fiscal antes de buscar tipos o parámetros.'
)]
class TaxRegimesListTool extends Tool
{
    public function handle(Request $request): Response
    {
        $rows = TaxRegime::query()
            ->when($request->input('scope'), fn (Builder $q, $s) => $q->where('scope', $s))
            ->when($request->input('search'), fn (Builder $q, $term) => $q->where(function (Builder $w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('scope')
            ->orderBy('code')
            ->get();

        return Response::json(McpResponseEnvelope::collection(
            McpTaxRegimeResource::collection($rows),
            source: 'AEAT — catálogo de regímenes tributarios',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()
                ->description('Ámbito: state (estatal), regional (autonómico), local.'),
            'search' => $schema->string()
                ->description('Texto libre — busca en code y name.'),
        ];
    }
}
