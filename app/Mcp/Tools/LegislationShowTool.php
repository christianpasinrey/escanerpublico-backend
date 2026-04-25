<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpLegislationNormResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Legislation\Models\LegislationNorm;

#[Description(
    'Devuelve la ficha de una disposición BOE. Acepta id interno o external_id '.
    '(formato BOE-A-YYYY-NNNN). Si recibe ambos, prevalece external_id.'
)]
class LegislationShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $externalId = trim((string) $request->input('external_id'));
        $id = (int) $request->input('id');

        if ($externalId === '' && $id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BOE — Boletín Oficial del Estado',
                note: 'Debes indicar id (entero) o external_id (BOE-A-YYYY-NNNN).',
            ));
        }

        $query = LegislationNorm::query()->with('organization');
        if ($externalId !== '') {
            $query->where('external_id', $externalId);
        } else {
            $query->whereKey($id);
        }

        $norm = $query->first();

        if ($norm === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BOE — Boletín Oficial del Estado',
                note: 'Disposición no encontrada.',
            ));
        }

        return Response::json(McpResponseEnvelope::single(
            McpLegislationNormResource::make($norm),
            source: 'BOE — Boletín Oficial del Estado',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID interno de la disposición (alternativa a external_id).'),
            'external_id' => $schema->string()
                ->description('Identificador BOE-A-YYYY-NNNN (preferido — coincide con el ELI).'),
        ];
    }
}
