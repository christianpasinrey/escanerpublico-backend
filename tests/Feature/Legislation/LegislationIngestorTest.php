<?php

namespace Tests\Feature\Legislation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Legislation\Models\BoeItem;
use Modules\Legislation\Models\BoeSummary;
use Modules\Legislation\Models\LegislationNorm;
use Modules\Legislation\Services\LegislationIngestor;
use Tests\TestCase;

class LegislationIngestorTest extends TestCase
{
    use RefreshDatabase;

    private LegislationIngestor $ingestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingestor = $this->app->make(LegislationIngestor::class);
    }

    public function test_ingest_consolidated_norm_resolves_organization(): void
    {
        $payload = $this->normPayload([
            'identificador' => 'BOE-A-2020-4859',
            'departamento' => ['codigo' => '9574', 'texto' => 'Ministerio de la Presidencia'],
        ]);

        $result = $this->ingestor->ingestConsolidatedNorm($payload);

        $this->assertSame('inserted', $result['action']);
        $this->assertNotNull($result['model']->organization_id);
        $org = Organization::find($result['model']->organization_id);
        $this->assertSame('Ministerio de la Presidencia', $org->name);
        $this->assertSame('9574', $org->identifier);
    }

    public function test_ingest_consolidated_norm_idempotent(): void
    {
        $payload = $this->normPayload(['identificador' => 'BOE-A-2024-1']);

        $this->ingestor->ingestConsolidatedNorm($payload);
        $second = $this->ingestor->ingestConsolidatedNorm($payload);

        $this->assertSame('skipped', $second['action']);
        $this->assertSame(1, LegislationNorm::count());
    }

    public function test_ingest_consolidated_norm_updates_when_changed(): void
    {
        $payload = $this->normPayload(['identificador' => 'BOE-A-2024-1', 'titulo' => 'Original']);
        $this->ingestor->ingestConsolidatedNorm($payload);

        $payload['titulo'] = 'Corregido';
        $second = $this->ingestor->ingestConsolidatedNorm($payload);
        $this->assertSame('updated', $second['action']);
        $this->assertSame('Corregido', LegislationNorm::first()->titulo);
    }

    public function test_ingest_consolidated_parses_boe_dates(): void
    {
        $payload = $this->normPayload([
            'identificador' => 'BOE-A-2024-1',
            'fecha_disposicion' => '20200505',
            'fecha_publicacion' => '20200507',
            'fecha_actualizacion' => '20260424T114500Z',
        ]);

        $result = $this->ingestor->ingestConsolidatedNorm($payload);
        $this->assertSame('2020-05-05', $result['model']->fecha_disposicion->toDateString());
        $this->assertSame('2020-05-07', $result['model']->fecha_publicacion->toDateString());
        $this->assertSame('2026-04-24 11:45:00', $result['model']->fecha_actualizacion->toDateTimeString());
    }

    public function test_ingest_daily_summary_creates_summary_and_items(): void
    {
        $summary = $this->summaryPayload();

        $result = $this->ingestor->ingestDailySummary($summary, '2026-04-24');

        $this->assertSame('BOE-S-2026-100', $result['summary']->identificador);
        $this->assertSame(2, $result['items_inserted']);
        $this->assertSame(0, $result['items_updated']);
        $this->assertSame(2, BoeItem::count());
        $this->assertSame(1, BoeSummary::count());
    }

    public function test_ingest_daily_summary_idempotent(): void
    {
        $summary = $this->summaryPayload();

        $this->ingestor->ingestDailySummary($summary, '2026-04-24');
        $second = $this->ingestor->ingestDailySummary($summary, '2026-04-24');

        $this->assertSame(0, $second['items_inserted']);
        $this->assertSame(1, BoeSummary::count());
        $this->assertSame(2, BoeItem::count());
    }

    public function test_ingest_daily_summary_resolves_dept_organizations(): void
    {
        $summary = $this->summaryPayload();
        $this->ingestor->ingestDailySummary($summary, '2026-04-24');

        $items = BoeItem::all();
        $this->assertNotNull($items[0]->organization_id);
        $org = Organization::find($items[0]->organization_id);
        $this->assertSame('MINISTERIO DE DEFENSA', $org->name);
        $this->assertSame('6110', $org->identifier);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function normPayload(array $overrides = []): array
    {
        return array_merge([
            'identificador' => 'BOE-A-2024-1',
            'fecha_actualizacion' => '20240101T120000Z',
            'ambito' => ['codigo' => '1', 'texto' => 'Estatal'],
            'departamento' => ['codigo' => '7723', 'texto' => 'Jefatura del Estado'],
            'rango' => ['codigo' => '1300', 'texto' => 'Ley'],
            'fecha_disposicion' => '20240101',
            'numero_oficial' => '1/2024',
            'titulo' => 'Ley 1/2024 de prueba',
            'fecha_publicacion' => '20240101',
            'fecha_vigencia' => '20240101',
            'vigencia_agotada' => 'N',
            'estado_consolidacion' => ['codigo' => '3', 'texto' => 'Finalizado'],
            'url_eli' => 'https://www.boe.es/eli/es/l/2024/01/01/1',
            'url_html_consolidada' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2024-1',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(): array
    {
        return [
            'metadatos' => ['publicacion' => 'BOE', 'fecha_publicacion' => '20260424'],
            'diario' => [[
                'numero' => '100',
                'sumario_diario' => [
                    'identificador' => 'BOE-S-2026-100',
                    'url_pdf' => ['szBytes' => '390475', 'texto' => 'https://www.boe.es/.../BOE-S-2026-100.pdf'],
                ],
                'seccion' => [[
                    'codigo' => '1',
                    'nombre' => 'I. Disposiciones generales',
                    'departamento' => [[
                        'codigo' => '6110',
                        'nombre' => 'MINISTERIO DE DEFENSA',
                        'epigrafe' => [[
                            'nombre' => 'Formación militar',
                            'item' => [
                                [
                                    'identificador' => 'BOE-A-2026-8955',
                                    'control' => '2026/6670',
                                    'titulo' => 'Orden DEF/372/2026...',
                                    'url_pdf' => ['texto' => 'https://www.boe.es/.../BOE-A-2026-8955.pdf', 'szBytes' => '510708'],
                                    'url_html' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2026-8955',
                                    'url_xml' => 'https://www.boe.es/diario_boe/xml.php?id=BOE-A-2026-8955',
                                ],
                                [
                                    'identificador' => 'BOE-A-2026-8956',
                                    'control' => '2026/6671',
                                    'titulo' => 'Otra orden DEF',
                                    'url_pdf' => ['texto' => 'https://www.boe.es/.../BOE-A-2026-8956.pdf', 'szBytes' => '120000'],
                                ],
                            ],
                        ]],
                    ]],
                ]],
            ]],
        ];
    }
}
