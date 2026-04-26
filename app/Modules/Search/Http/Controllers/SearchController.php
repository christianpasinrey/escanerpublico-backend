<?php

namespace Modules\Search\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Search\Http\Resources\SearchBucketResource;
use Modules\Search\Services\FederatedSearchService;

/**
 * Búsqueda federada cross-módulo.
 *
 * `GET /api/v1/search?q=<query>&limit=<int>` — devuelve hits agrupados por
 * dominio (contracts, organizations, companies, subsidies, legislation,
 * officials, …). El controller no conoce los módulos: delega al servicio,
 * que a su vez itera sobre los providers etiquetados.
 *
 * Pensado para alimentar al spotlight Cmd+K del frontend público y a la
 * tool MCP `search_everywhere`.
 */
class SearchController
{
    public function __construct(
        private readonly FederatedSearchService $service,
    ) {}

    /**
     * Federated search across all public domains.
     *
     * Returns up to `limit` hits per bucket. Empty `q` (or shorter than
     * 2 chars) yields an empty bucket list — the API does not error on
     * empty queries to keep the spotlight UI snappy on first focus.
     *
     * @response array{
     *   data: array{
     *     query: string,
     *     total_hits: int,
     *     took_ms: number,
     *     buckets: array<int, array{
     *       key: string,
     *       label: string,
     *       total: int,
     *       hits: array<int, array{
     *         type: string,
     *         id: string|int,
     *         title: string,
     *         subtitle: string|null,
     *         url: string,
     *         api_url: string,
     *         meta: object
     *       }>
     *     }>
     *   }
     * }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $query = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? FederatedSearchService::DEFAULT_LIMIT_PER_BUCKET);

        $results = $this->service->search($query, $limit);

        return response()->json([
            'data' => [
                'query' => $results->query,
                'total_hits' => $results->total_hits,
                'took_ms' => $results->took_ms,
                'buckets' => SearchBucketResource::collection($results->buckets),
            ],
        ]);
    }
}
