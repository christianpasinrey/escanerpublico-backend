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
    'Devuelve la ficha completa de un contrato PLACSP por su external_id (clave estable del feed). '.
    'Incluye organismo, lotes y adjudicaciones. Si el contrato no existe responde count=0 sin lanzar error.'
)]
class ContractShowTool extends Tool
{
    public function handle(Request $request): Response
    {
        $externalId = (string) $request->input('external_id');

        if ($externalId === '') {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — Plataforma de Contratación del Sector Público',
                note: 'external_id es obligatorio.',
            ));
        }

        $contract = Contract::query()
            ->where('external_id', $externalId)
            ->with(['organization', 'lots.awards.company'])
            ->first();

        if ($contract === null) {
            return Response::json(McpResponseEnvelope::empty(
                source: 'PLACSP — Plataforma de Contratación del Sector Público',
                note: "No se encontró ningún contrato con external_id={$externalId}.",
            ));
        }

        return Response::json(McpResponseEnvelope::single(
            McpContractResource::make($contract),
            source: 'PLACSP — Plataforma de Contratación del Sector Público',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'external_id' => $schema->string()
                ->required()
                ->description('Identificador estable del contrato en PLACSP (ej: 0000000123456789).'),
        ];
    }
}
