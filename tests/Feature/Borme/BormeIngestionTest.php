<?php

namespace Tests\Feature\Borme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Borme\Jobs\FetchBormeSumarioJob;
use Modules\Borme\Jobs\ProcessBormePdfJob;
use Modules\Borme\Models\BormeIngestRun;
use Modules\Borme\Models\BormePdf;
use Modules\Borme\Services\BormeApiClient;
use Modules\Borme\Services\SumarioParser;
use Tests\TestCase;

class BormeIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sumario_parser_flattens_pdfs_with_province_codes(): void
    {
        $sumario = [
            'metadatos' => ['publicacion' => 'BORME', 'fecha_publicacion' => '20260424'],
            'diario' => [[
                'numero' => '78',
                'seccion' => [[
                    'codigo' => 'A',
                    'item' => [
                        [
                            'identificador' => 'BORME-A-2026-78-28',
                            'titulo' => 'MADRID',
                            'url_pdf' => ['texto' => 'https://www.boe.es/borme/dias/2026/04/24/pdfs/BORME-A-2026-78-28.pdf'],
                        ],
                        [
                            'identificador' => 'BORME-A-2026-78-08',
                            'titulo' => 'BARCELONA',
                            'url_pdf' => ['texto' => 'https://www.boe.es/borme/dias/2026/04/24/pdfs/BORME-A-2026-78-08.pdf'],
                        ],
                    ],
                ]],
            ]],
        ];

        $rows = (new SumarioParser)->flattenPdfs($sumario);

        $this->assertCount(2, $rows);
        $this->assertSame('BORME-A-2026-78-28', $rows[0]['cve']);
        $this->assertSame('A', $rows[0]['section']);
        $this->assertSame('28', $rows[0]['province_ine']);
        $this->assertSame('MADRID', $rows[0]['province_name']);
        $this->assertSame('2026-04-24', $rows[0]['date']);
        $this->assertSame(78, $rows[0]['bulletin_no']);
    }

    public function test_api_client_returns_null_on_404(): void
    {
        Http::fake([
            'boe.es/datosabiertos/api/borme/sumario/20990101*' => Http::response('', 404),
        ]);

        $this->assertNull((new BormeApiClient)->getDailySummary('20990101'));
    }

    public function test_fetch_job_creates_pdf_rows_and_chains_processing(): void
    {
        Http::fake([
            'boe.es/datosabiertos/api/borme/sumario/20260424*' => Http::response([
                'status' => ['code' => '200', 'text' => 'ok'],
                'data' => ['sumario' => [
                    'metadatos' => ['publicacion' => 'BORME', 'fecha_publicacion' => '20260424'],
                    'diario' => [[
                        'numero' => '78',
                        'seccion' => [[
                            'codigo' => 'A',
                            'item' => [[
                                'identificador' => 'BORME-A-2026-78-28',
                                'titulo' => 'MADRID',
                                'url_pdf' => ['texto' => 'https://example.test/madrid.pdf'],
                            ]],
                        ]],
                    ]],
                ]],
            ], 200),
        ]);

        $run = BormeIngestRun::create([
            'type' => 'daily',
            'from_date' => '2026-04-24',
            'to_date' => '2026-04-24',
            'cursor_date' => '2026-04-24',
            'status' => 'running',
            'started_at' => now(),
        ]);

        Bus::fake();
        $job = new FetchBormeSumarioJob('20260424', $run->id);
        $job->handle($this->app->make(BormeApiClient::class), $this->app->make(SumarioParser::class));

        $pdf = BormePdf::where('cve', 'BORME-A-2026-78-28')->first();
        $this->assertNotNull($pdf);
        $this->assertSame($run->id, $pdf->borme_ingest_run_id);
        $this->assertSame('A', $pdf->section);
        $this->assertSame('28', $pdf->province_ine);
        $this->assertSame('pending', $pdf->status);

        Bus::assertDispatched(ProcessBormePdfJob::class, 1);

        $run->refresh();
        $this->assertSame(1, $run->total_pdfs);
    }

    public function test_console_command_dispatches_jobs_per_date(): void
    {
        Bus::fake();

        $this->artisan('borme:sync', ['--date' => '2026-04-24'])->assertSuccessful();

        Bus::assertDispatched(FetchBormeSumarioJob::class, 1);
        $this->assertSame(1, BormeIngestRun::count());
    }
}
