<?php

namespace App\Mcp\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Sobre estándar para respuestas de tools MCP. Encapsula la `data` del
 * resource (item u items) con la cita de fuente oficial y la licencia
 * — dato obligatorio del proyecto: cualquier respuesta debe llevar la
 * fuente para que el consumidor pueda verificarla.
 *
 * Uso:
 *
 *   return Response::json(McpResponseEnvelope::collection(
 *       McpContractResource::collection($rows),
 *       source: 'PLACSP — Plataforma de Contratación del Sector Público',
 *   ));
 *
 *   return Response::json(McpResponseEnvelope::single(
 *       McpContractResource::make($contract),
 *       source: 'PLACSP',
 *   ));
 */
final class McpResponseEnvelope
{
    public const LICENSE = 'CC-BY 4.0';

    /**
     * @return array<string, mixed>
     */
    public static function collection(
        ResourceCollection $collection,
        string $source,
        ?string $note = null,
    ): array {
        $items = $collection->resolve();

        return self::wrap([
            'count' => count($items),
            'items' => $items,
        ], $source, $note);
    }

    /**
     * @return array<string, mixed>
     */
    public static function single(
        \Illuminate\Http\Resources\Json\JsonResource $resource,
        string $source,
        ?string $note = null,
    ): array {
        return self::wrap([
            'item' => $resource->resolve(),
        ], $source, $note);
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(string $source, ?string $note = null): array
    {
        return self::wrap([
            'count' => 0,
            'items' => [],
        ], $source, $note);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function wrap(array $payload, string $source, ?string $note): array
    {
        $envelope = [
            ...$payload,
            'source' => $source,
            'license' => self::LICENSE,
        ];

        if ($note !== null) {
            $envelope['note'] = $note;
        }

        return $envelope;
    }
}
