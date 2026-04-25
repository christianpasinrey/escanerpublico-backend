<?php

namespace Tests\Feature\Subsidies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;
use Modules\Subsidies\Models\SubsidyCall;
use Modules\Subsidies\Models\SubsidyGrant;
use Modules\Subsidies\Models\SubsidyParseError;
use Modules\Subsidies\Models\SubsidySnapshot;
use Modules\Subsidies\Services\SubsidyIngestor;
use Tests\TestCase;

class SubsidyIngestorTest extends TestCase
{
    use RefreshDatabase;

    private SubsidyIngestor $ingestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ingestor = $this->app->make(SubsidyIngestor::class);
    }

    public function test_ingests_call_creates_row_and_resolves_organization(): void
    {
        $payload = $this->callPayload([
            'id' => 1103144,
            'numeroConvocatoria' => '901583',
            'descripcion' => 'BASES PROGRAMA MARELA',
            'fechaRecepcion' => '2026-04-24',
            'nivel1' => 'LOCAL',
            'nivel2' => 'DIPUTACION PROV. DE LUGO',
            'nivel3' => 'DIPUTACION PROVINCIAL DE LUGO',
        ]);

        $result = $this->ingestor->ingestCall($payload);

        $this->assertSame('inserted', $result['action']);
        $this->assertSame('BDNS', $result['model']->source);
        $this->assertSame(1103144, $result['model']->external_id);
        $this->assertNotNull($result['model']->organization_id);

        $org = Organization::find($result['model']->organization_id);
        $this->assertSame('DIPUTACION PROVINCIAL DE LUGO', $org->name);
        $this->assertSame('DIPUTACION PROV. DE LUGO', $org->parent_name);
    }

    public function test_ingest_call_idempotent_on_same_payload(): void
    {
        $payload = $this->callPayload(['id' => 999, 'descripcion' => 'foo']);

        $first = $this->ingestor->ingestCall($payload);
        $this->assertSame('inserted', $first['action']);

        $second = $this->ingestor->ingestCall($payload);
        $this->assertSame('skipped', $second['action']);

        $this->assertSame(1, SubsidyCall::count());
    }

    public function test_ingest_call_updates_when_payload_changes(): void
    {
        $payload = $this->callPayload(['id' => 999, 'descripcion' => 'foo']);
        $this->ingestor->ingestCall($payload);

        $payload['descripcion'] = 'foo (corregido)';
        $second = $this->ingestor->ingestCall($payload);
        $this->assertSame('updated', $second['action']);

        $this->assertSame('foo (corregido)', SubsidyCall::first()->description);
    }

    public function test_ingest_grant_resolves_company_by_nif(): void
    {
        $payload = $this->grantPayload([
            'id' => 150295544,
            'beneficiario' => 'B12345678 ACME CONSTRUCCIONES SL',
            'importe' => 18375.0,
            'fechaConcesion' => '2026-04-24',
            'nivel2' => 'CASTILLA-LA MANCHA',
            'nivel3' => 'VICECONSEJERIA AGRARIA',
        ]);

        $result = $this->ingestor->ingestGrant($payload);

        $this->assertSame('inserted', $result['action']);
        $this->assertNotNull($result['model']->company_id);
        $this->assertSame('B12345678', $result['model']->beneficiario_nif);
        $this->assertSame('ACME CONSTRUCCIONES SL', $result['model']->beneficiario_name);

        $company = Company::find($result['model']->company_id);
        $this->assertSame('B12345678', $company->nif);
    }

    public function test_ingest_grant_creates_snapshot(): void
    {
        $payload = $this->grantPayload(['id' => 1]);

        $this->ingestor->ingestGrant($payload);

        $this->assertSame(1, SubsidySnapshot::count());
        $snapshot = SubsidySnapshot::first();
        $this->assertEquals(1, $snapshot->raw_payload['id']);
    }

    public function test_ingest_grant_idempotent_does_not_duplicate_snapshots(): void
    {
        $payload = $this->grantPayload(['id' => 1]);

        $this->ingestor->ingestGrant($payload);
        $this->ingestor->ingestGrant($payload);
        $this->ingestor->ingestGrant($payload);

        $this->assertSame(1, SubsidyGrant::count());
        $this->assertSame(1, SubsidySnapshot::count());
    }

    public function test_ingest_grant_records_new_snapshot_when_payload_changes(): void
    {
        $payload = $this->grantPayload(['id' => 1, 'importe' => 1000]);
        $this->ingestor->ingestGrant($payload);

        $payload['importe'] = 2000;
        $result = $this->ingestor->ingestGrant($payload);

        $this->assertSame('updated', $result['action']);
        $this->assertSame(2, SubsidySnapshot::count());
        $this->assertSame(2000.0, (float) SubsidyGrant::first()->amount);
    }

    public function test_ingest_grant_links_to_existing_call(): void
    {
        $callPayload = $this->callPayload(['id' => 887927]);
        $this->ingestor->ingestCall($callPayload);

        $grantPayload = $this->grantPayload(['id' => 1, 'idConvocatoria' => 887927]);
        $result = $this->ingestor->ingestGrant($grantPayload);

        $this->assertNotNull($result['model']->call_id);
        $this->assertSame(887927, $result['model']->external_call_id);
    }

    public function test_ingest_grant_records_parse_error_on_unparseable_beneficiario(): void
    {
        $payload = $this->grantPayload([
            'id' => 1,
            'beneficiario' => 'AYUNTAMIENTO DE FOO sin nif identificable',
        ]);

        $this->ingestor->ingestGrant($payload);

        $this->assertSame(1, SubsidyParseError::count());
        $error = SubsidyParseError::first();
        $this->assertSame('beneficiario', $error->field);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function callPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'mrr' => false,
            'numeroConvocatoria' => '100000',
            'descripcion' => 'Test convocatoria',
            'descripcionLeng' => null,
            'fechaRecepcion' => '2026-01-01',
            'nivel1' => 'LOCAL',
            'nivel2' => 'AYUNTAMIENTO DE PRUEBA',
            'nivel3' => 'AYUNTAMIENTO DE PRUEBA',
            'codigoInvente' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function grantPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'codConcesion' => 'SB1',
            'fechaConcesion' => '2026-01-01',
            'beneficiario' => 'B11111111 EMPRESA TEST SL',
            'instrumento' => 'SUBVENCION',
            'importe' => 1000.0,
            'ayudaEquivalente' => 1000.0,
            'urlBR' => null,
            'tieneProyecto' => false,
            'numeroConvocatoria' => '100000',
            'idConvocatoria' => 1,
            'convocatoria' => 'Convocatoria test',
            'descripcionCooficial' => null,
            'nivel1' => 'AUTONOMICA',
            'nivel2' => 'COMUNIDAD TEST',
            'nivel3' => 'CONSEJERIA TEST',
            'codigoInvente' => null,
            'idPersona' => 12345,
            'fechaAlta' => '2026-01-01',
        ], $overrides);
    }
}
