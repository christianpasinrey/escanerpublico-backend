<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpOfficialDetailResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Officials\Models\PublicOfficial;

#[Description(
    'Trayectoria completa de un cargo público: lista todos sus nombramientos, '.
    'ceses y tomas de posesión con cita al BOE original (boe_url).'
)]
class OfficialShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BOE — Sección II.A',
                note: 'id es obligatorio.',
            ));
        }

        $official = PublicOfficial::query()
            ->with(['appointments.boeItem', 'appointments.organization'])
            ->find($id);

        if ($official === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'BOE — Sección II.A',
                note: "Cargo público {$id} no encontrado.",
            ));
        }

        return Response::json(McpResponseEnvelope::single(
            McpOfficialDetailResource::make($official),
            source: 'BOE — Sección II.A',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('ID interno del cargo público (ver officials_search).'),
        ];
    }
}
