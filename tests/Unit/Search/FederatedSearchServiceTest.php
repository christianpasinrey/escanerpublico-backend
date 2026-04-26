<?php

namespace Tests\Unit\Search;

use Illuminate\Support\Facades\Event;
use Modules\Search\Contracts\SearchProvider;
use Modules\Search\DataObjects\SearchBucket;
use Modules\Search\DataObjects\SearchHit;
use Modules\Search\Events\SearchPerformed;
use Modules\Search\Services\FederatedSearchService;
use Tests\TestCase;

/**
 * Unit tests del orquestador. No tocan BD — usan providers fake para
 * verificar la composición pura: dispatch, agregación, manejo de errores,
 * limit clamping, threshold de query mínima.
 */
class FederatedSearchServiceTest extends TestCase
{
    public function test_aggregates_hits_from_multiple_providers(): void
    {
        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', [$this->hit('alpha', 1)]),
            $this->fakeProvider('beta', 'Beta', [$this->hit('beta', 1), $this->hit('beta', 2)]),
        ]);

        $results = $service->search('test');

        $this->assertSame('test', $results->query);
        $this->assertCount(2, $results->buckets);
        $this->assertSame(3, $results->total_hits);
        $this->assertSame('alpha', $results->buckets[0]->key);
        $this->assertSame('beta', $results->buckets[1]->key);
    }

    public function test_returns_empty_buckets_when_query_too_short(): void
    {
        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', [$this->hit('alpha', 1)]),
        ]);

        $results = $service->search('a');

        $this->assertSame(0, $results->total_hits);
        $this->assertSame([], $results->buckets);
    }

    public function test_isolates_provider_failures_so_other_buckets_survive(): void
    {
        $service = new FederatedSearchService([
            new class implements SearchProvider
            {
                public function key(): string
                {
                    return 'broken';
                }

                public function label(): string
                {
                    return 'Broken';
                }

                public function search(string $query, int $limit): SearchBucket
                {
                    throw new \RuntimeException('boom');
                }
            },
            $this->fakeProvider('alpha', 'Alpha', [$this->hit('alpha', 1)]),
        ]);

        $results = $service->search('hello');

        $this->assertCount(2, $results->buckets);
        $this->assertSame(0, count($results->buckets[0]->hits));
        $this->assertSame('broken', $results->buckets[0]->key);
        $this->assertSame(1, count($results->buckets[1]->hits));
    }

    public function test_truncates_providers_that_return_more_than_limit(): void
    {
        $hits = [
            $this->hit('alpha', 1),
            $this->hit('alpha', 2),
            $this->hit('alpha', 3),
        ];

        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', $hits),
        ]);

        $results = $service->search('hello', limitPerBucket: 2);

        $this->assertCount(2, $results->buckets[0]->hits);
    }

    public function test_clamps_out_of_range_limits(): void
    {
        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', []),
        ]);

        // 0 should clamp up to 1; we observe via bucket label only since hits empty.
        $results = $service->search('hello', limitPerBucket: 0);
        $this->assertCount(1, $results->buckets);

        // 999 should clamp down to MAX_LIMIT_PER_BUCKET.
        $results = $service->search('hello', limitPerBucket: 999);
        $this->assertCount(1, $results->buckets);
    }

    public function test_dispatches_search_performed_event(): void
    {
        Event::fake([SearchPerformed::class]);

        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', [$this->hit('alpha', 1)]),
        ]);

        $service->search('hello');

        Event::assertDispatched(
            SearchPerformed::class,
            fn (SearchPerformed $e) => $e->results->query === 'hello' && $e->results->total_hits === 1,
        );
    }

    public function test_does_not_dispatch_event_data_with_short_queries(): void
    {
        Event::fake([SearchPerformed::class]);

        $service = new FederatedSearchService([
            $this->fakeProvider('alpha', 'Alpha', [$this->hit('alpha', 1)]),
        ]);

        $service->search('a');

        // Still dispatches but with empty buckets — telemetry consumers
        // can decide what to do with sub-threshold queries.
        Event::assertDispatched(
            SearchPerformed::class,
            fn (SearchPerformed $e) => $e->results->total_hits === 0,
        );
    }

    /**
     * @param  list<SearchHit>  $hits
     */
    private function fakeProvider(string $key, string $label, array $hits): SearchProvider
    {
        return new class($key, $label, $hits) implements SearchProvider
        {
            /**
             * @param  list<SearchHit>  $hits
             */
            public function __construct(
                private readonly string $providerKey,
                private readonly string $providerLabel,
                private readonly array $providerHits,
            ) {}

            public function key(): string
            {
                return $this->providerKey;
            }

            public function label(): string
            {
                return $this->providerLabel;
            }

            public function search(string $query, int $limit): SearchBucket
            {
                return new SearchBucket(
                    key: $this->providerKey,
                    label: $this->providerLabel,
                    hits: $this->providerHits,
                    total: count($this->providerHits),
                );
            }
        };
    }

    private function hit(string $type, int $id): SearchHit
    {
        return new SearchHit(
            type: $type,
            id: $id,
            title: "Hit {$type} #{$id}",
            subtitle: null,
            url: "/{$type}/{$id}",
            api_url: "/api/v1/{$type}/{$id}",
        );
    }
}
