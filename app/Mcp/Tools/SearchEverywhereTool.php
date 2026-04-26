<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Search\DataObjects\SearchHit;
use Modules\Search\Services\FederatedSearchService;

#[Description(
    'Búsqueda federada cross-módulo. Devuelve hits agrupados por dominio: '.
    'contratos, organismos, empresas, convocatorias, subvenciones, legislación, cargos públicos. '.
    'Pensada como punto de entrada para abrir cualquier conversación: con un solo término el '.
    'agente recoge todo lo que el archivo público sabe sobre ese nombre/NIF/expediente.'
)]
class SearchEverywhereTool extends Tool
{
    public function __construct(
        private readonly FederatedSearchService $service,
    ) {}

    public function handle(Request $request): Response
    {
        $query = (string) ($request->input('q') ?: '');
        $limit = (int) ($request->input('limit_per_bucket') ?: FederatedSearchService::DEFAULT_LIMIT_PER_BUCKET);

        $results = $this->service->search($query, $limit);

        return Response::json([
            'query' => $results->query,
            'total_hits' => $results->total_hits,
            'took_ms' => $results->took_ms,
            'buckets' => array_map(
                fn ($bucket) => [
                    'key' => $bucket->key,
                    'label' => $bucket->label,
                    'total' => $bucket->total,
                    'hits' => array_map($this->shapeHit(...), $bucket->hits),
                ],
                $results->buckets,
            ),
            'source' => 'GobTracker — búsqueda federada cross-módulo (PLACSP/BDNS/BOE/DIR3)',
            'license' => 'CC-BY 4.0',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Término a buscar (mínimo 2 caracteres). Acepta nombres de empresa, NIF, número de expediente, identificador BOE-A-YYYY-NNNN, nombre de organismo, etc.'),
            'limit_per_bucket' => $schema->integer()->description('Máximo de hits por dominio (1-20, por defecto 5). Total = limit × N dominios.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeHit(SearchHit $hit): array
    {
        $publicBase = 'https://gobtracker.tailor-bytes.com';
        $apiBase = 'https://app.gobtracker.tailor-bytes.com';

        return [
            'type' => $hit->type,
            'id' => $hit->id,
            'title' => $hit->title,
            'subtitle' => $hit->subtitle,
            'public_url' => $publicBase.$hit->url,
            'detail_url' => $apiBase.$hit->api_url,
            'meta' => $hit->meta,
        ];
    }
}
