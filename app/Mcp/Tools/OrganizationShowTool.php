<?php

namespace App\Mcp\Tools;

use App\Mcp\Resources\McpOrganizationDetailResource;
use App\Mcp\Resources\McpResponseEnvelope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contracts\Models\Organization;

#[Description(
    'Ficha completa de un organismo contratante. Incluye DIR3, hierarchy, '.
    'direcciones postales y contactos asociados.'
)]
class OrganizationShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = (int) $request->input('id');

        if ($id <= 0) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'DIR3 — Directorio Común de Unidades Orgánicas y Oficinas',
                note: 'id es obligatorio y debe ser un entero positivo.',
            ));
        }

        $org = Organization::query()
            ->with(['addresses', 'contacts'])
            ->find($id);

        if ($org === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'DIR3 — Directorio Común de Unidades Orgánicas y Oficinas',
                note: "No se encontró ningún organismo con id={$id}.",
            ));
        }

        return Response::json(McpResponseEnvelope::single(
            McpOrganizationDetailResource::make($org),
            source: 'DIR3 — Directorio Común de Unidades Orgánicas y Oficinas',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->required()
                ->description('ID interno del organismo (ver organizations_search).'),
        ];
    }
}
