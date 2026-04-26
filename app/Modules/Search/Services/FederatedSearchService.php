<?php

namespace Modules\Search\Services;

use Illuminate\Support\Facades\Event;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchResults;
use Modules\Search\Events\SearchPerformed;

/**
 * Despacha la búsqueda a cada provider registrado vía container tagging y
 * agrega los resultados. Sin lógica de dominio aquí — solo orquestación.
 *
 * Los providers se inyectan como `iterable<SearchProvider>` desde el
 * container (`tagged('search.providers')`). El servicio NO conoce a Contract,
 * Organization, LegislationNorm, etc.
 *
 * Errores aislados: si un provider lanza, se ignora su bucket y el resto
 * sigue. La búsqueda federada es best-effort — preferible devolver 5 buckets
 * que tirar la respuesta entera por un fallo de un dominio.
 */
class FederatedSearchService
{
    public const MIN_QUERY_LENGTH = 2;

    public const DEFAULT_LIMIT_PER_BUCKET = 5;

    public const MAX_LIMIT_PER_BUCKET = 20;

    /**
     * @param  iterable<SearchProvider>  $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {}

    public function search(string $query, int $limitPerBucket = self::DEFAULT_LIMIT_PER_BUCKET): SearchResults
    {
        $startedAt = microtime(true);
        $query = trim($query);
        $limitPerBucket = max(1, min(self::MAX_LIMIT_PER_BUCKET, $limitPerBucket));

        $buckets = [];
        $totalHits = 0;

        if (mb_strlen($query) >= self::MIN_QUERY_LENGTH) {
            foreach ($this->providers as $provider) {
                $bucket = $this->safelyRun($provider, $query, $limitPerBucket);
                $buckets[] = $bucket;
                $totalHits += count($bucket->hits);
            }
        }

        $tookMs = round((microtime(true) - $startedAt) * 1000, 2);

        $results = new SearchResults(
            query: $query,
            buckets: $buckets,
            total_hits: $totalHits,
            took_ms: $tookMs,
        );

        Event::dispatch(new SearchPerformed($results));

        return $results;
    }

    private function safelyRun(SearchProvider $provider, string $query, int $limit): SearchBucket
    {
        try {
            $bucket = $provider->search($query, $limit);

            // Defensa: si un provider devuelve más hits que el límite acordado,
            // truncamos. La idea es que no pueda saturar la respuesta.
            if (count($bucket->hits) > $limit) {
                return new SearchBucket(
                    key: $bucket->key,
                    label: $bucket->label,
                    hits: array_slice($bucket->hits, 0, $limit),
                    total: $bucket->total,
                );
            }

            return $bucket;
        } catch (\Throwable $e) {
            report($e);

            return SearchBucket::empty($provider->key(), $provider->label());
        }
    }
}
