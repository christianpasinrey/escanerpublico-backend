<?php

namespace Modules\Search\DataObjects;

/**
 * Respuesta agregada del servicio federado: la query original, los buckets
 * de cada dominio y la latencia total. Es lo que sale por la API y por la
 * tool MCP sin transformación adicional más allá del Resource.
 */
final class SearchResults
{
    /**
     * @param  list<SearchBucket>  $buckets
     */
    public function __construct(
        public readonly string $query,
        public readonly array $buckets,
        public readonly int $total_hits,
        public readonly float $took_ms,
    ) {}
}
