<?php

namespace Tests\Feature\Subsidies;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Subsidies\Services\BdnsClient;
use Tests\TestCase;

class BdnsClientTest extends TestCase
{
    public function test_search_calls_returns_content_array(): void
    {
        Http::fake([
            'infosubvenciones.es/bdnstrans/api/convocatorias/busqueda*' => Http::response([
                'content' => [
                    ['id' => 1, 'descripcion' => 'foo'],
                    ['id' => 2, 'descripcion' => 'bar'],
                ],
                'totalElements' => 2,
                'totalPages' => 1,
                'last' => true,
                'first' => true,
            ], 200),
        ]);

        $client = new BdnsClient;
        $page = $client->searchCalls(0, 100);

        $this->assertCount(2, $page['content']);
        $this->assertSame(1, $page['content'][0]['id']);
    }

    public function test_search_grants_passes_filters_in_query_string(): void
    {
        Http::fake([
            'infosubvenciones.es/bdnstrans/api/concesiones/busqueda*' => Http::response([
                'content' => [], 'totalElements' => 0, 'totalPages' => 0, 'last' => true, 'first' => true,
            ], 200),
        ]);

        $client = new BdnsClient;
        $client->searchGrants(2, 50, ['fechaDesde' => '01/01/2024']);

        Http::assertSent(function (Request $req) {
            $url = (string) $req->url();

            return str_contains($url, 'page=2')
                && str_contains($url, 'pageSize=50')
                && str_contains($url, 'fechaDesde=')
                && str_starts_with($url, 'https://www.infosubvenciones.es/bdnstrans/api/concesiones/busqueda');
        });
    }

    public function test_includes_user_agent_header(): void
    {
        Http::fake([
            '*' => Http::response(['content' => [], 'totalElements' => 0, 'totalPages' => 0, 'last' => true, 'first' => true], 200),
        ]);

        $client = new BdnsClient;
        $client->searchCalls(0, 1);

        Http::assertSent(fn (Request $req) => $req->header('User-Agent')[0] === BdnsClient::USER_AGENT);
    }

    public function test_throws_on_5xx_after_retries(): void
    {
        Http::fake([
            '*' => Http::response('boom', 503),
        ]);

        $this->expectException(RequestException::class);

        $client = new BdnsClient;
        $client->searchCalls(0, 1);
    }

    public function test_throws_when_response_missing_content_key(): void
    {
        Http::fake([
            '*' => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing "content"/');

        $client = new BdnsClient;
        $client->searchCalls(0, 1);
    }
}
