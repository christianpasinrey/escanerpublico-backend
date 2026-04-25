<?php

namespace Tests\Feature\Legislation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Legislation\Services\BoeClient;
use Tests\TestCase;

class BoeClientTest extends TestCase
{
    public function test_get_daily_summary_returns_sumario_node(): void
    {
        Http::fake([
            'boe.es/datosabiertos/api/boe/sumario/20260424*' => Http::response([
                'status' => ['code' => '200', 'text' => 'ok'],
                'data' => ['sumario' => [
                    'metadatos' => ['publicacion' => 'BOE', 'fecha_publicacion' => '20260424'],
                    'diario' => [['numero' => '100', 'sumario_diario' => ['identificador' => 'BOE-S-2026-100']]],
                ]],
            ], 200),
        ]);

        $client = new BoeClient;
        $summary = $client->getDailySummary('20260424');

        $this->assertNotNull($summary);
        $this->assertSame('BOE', $summary['metadatos']['publicacion']);
        $this->assertCount(1, $summary['diario']);
    }

    public function test_get_daily_summary_returns_null_on_404(): void
    {
        Http::fake([
            '*' => Http::response('not found', 404),
        ]);
        $client = new BoeClient;
        $this->assertNull($client->getDailySummary('20990101'));
    }

    public function test_search_consolidated_legislation_passes_offset_and_limit(): void
    {
        Http::fake([
            'boe.es/datosabiertos/api/legislacion-consolidada*' => Http::response([
                'status' => ['code' => '200'],
                'data' => [],
            ], 200),
        ]);

        $client = new BoeClient;
        $client->searchConsolidatedLegislation(100, 25);

        Http::assertSent(function (Request $req) {
            $url = (string) $req->url();

            return str_contains($url, 'offset=100') && str_contains($url, 'limit=25');
        });
    }

    public function test_includes_user_agent(): void
    {
        Http::fake([
            '*' => Http::response(['status' => ['code' => '200'], 'data' => []], 200),
        ]);
        $client = new BoeClient;
        $client->searchConsolidatedLegislation(0, 1);

        Http::assertSent(fn (Request $req) => $req->header('User-Agent')[0] === BoeClient::USER_AGENT);
    }
}
