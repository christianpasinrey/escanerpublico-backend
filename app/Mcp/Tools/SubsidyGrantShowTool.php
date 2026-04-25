<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpResponseEnvelope;
use App\Mcp\Resources\McpSubsidyGrantResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Subsidies\Models\SubsidyGrant;

#[Description(
    'Ficha completa de una concesión BDNS por id interno. Incluye la convocatoria '.
    'asociada cargada vía relación.'
)]
class SubsidyGrantShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BDNS — concesiones individuales',
                note: 'id es obligatorio.',
            ));
        }

        $grant = SubsidyGrant::query()
            ->with(['call', 'organization', 'company'])
            ->find($id);

        if ($grant === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BDNS — concesiones individuales',
                note: "Concesión {$id} no encontrada.",
            ));
        }

        return Response::json(McpResponseEnvelope::single(
            McpSubsidyGrantResource::make($grant),
            source: 'BDNS — concesiones individuales',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('ID interno de la concesión.'),
        ];
    }
}
