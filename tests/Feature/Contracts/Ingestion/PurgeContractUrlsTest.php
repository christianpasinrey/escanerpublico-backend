<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Support\Facades\Http;
use Modules\Contracts\Jobs\PurgeContractUrls;
use Tests\TestCase;

class PurgeContractUrlsTest extends TestCase
{
    public function test_calls_cloudflare_purge_api_with_batched_urls(): void
    {
        config([
            'cloudflare.api_token' => 'test-token',
            'cloudflare.zone_id' => 'zone-x',
            'cloudflare.base_url' => 'https://api.example/',
        ]);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $job = new PurgeContractUrls([1, 2, 3]);
        $job->handle(app(\Modules\Contracts\Services\Cache\CloudflarePurger::class));

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'zones/zone-x/purge_cache')
                && count($body['files'] ?? []) >= 3;
        });
    }

    public function test_skips_when_no_token_configured(): void
    {
        config(['cloudflare.api_token' => null]);
        Http::fake();

        (new PurgeContractUrls([1]))->handle(app(\Modules\Contracts\Services\Cache\CloudflarePurger::class));

        Http::assertNothingSent();
    }
}
