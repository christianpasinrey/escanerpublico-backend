<?php

namespace Tests\Feature\Tax\Api;

use Database\Seeders\Modules\Tax\Parameters\TaxParameters2025Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxParameters2025Seeder::class);
    }

    public function test_parameters_endpoint_returns_data(): void
    {
        $response = $this->getJson('/api/v1/tax/parameters?filter[year]=2025');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'year', 'region_code', 'key', 'value', 'source_url'],
                ],
                'meta',
            ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_parameters_filter_by_key(): void
    {
        $response = $this->getJson('/api/v1/tax/parameters?filter[year]=2025&filter[key]=irpf.minimo_personal_general');

        $response->assertSuccessful();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $row) {
            $this->assertSame('irpf.minimo_personal_general', $row['key']);
        }
    }

    public function test_brackets_endpoint(): void
    {
        $response = $this->getJson('/api/v1/tax/brackets?filter[year]=2025&filter[scope]=state&filter[type]=irpf_general');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'year', 'scope', 'region_code', 'type', 'from_amount', 'rate'],
                ],
            ]);

        $brackets = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($brackets), 'Escala IRPF estatal debería tener al menos 5 tramos');
    }

    public function test_brackets_regional_filter(): void
    {
        $response = $this->getJson('/api/v1/tax/brackets?filter[year]=2025&filter[scope]=regional&filter[region_code]=MD');

        $response->assertSuccessful();
        foreach ($response->json('data') as $row) {
            $this->assertSame('MD', $row['region_code']);
            $this->assertSame('regional', $row['scope']);
        }
    }

    public function test_social_security_rates_endpoint(): void
    {
        $response = $this->getJson('/api/v1/tax/social-security-rates?filter[year]=2025&filter[regime]=RG');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'year', 'regime', 'contingency', 'rate_employer', 'rate_employee'],
                ],
            ]);

        $rates = $response->json('data');
        $this->assertNotEmpty($rates);
        foreach ($rates as $row) {
            $this->assertSame('RG', $row['regime']);
        }
    }

    public function test_autonomo_brackets_endpoint(): void
    {
        $response = $this->getJson('/api/v1/tax/autonomo-brackets?filter[year]=2025');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'year', 'bracket_number', 'from_yield', 'monthly_quota_min', 'monthly_quota_max'],
                ],
            ]);

        $brackets = $response->json('data');
        $this->assertCount(15, $brackets, 'RD-ley 13/2022 define 15 tramos');
    }

    public function test_vat_product_rates_endpoint(): void
    {
        $response = $this->getJson('/api/v1/tax/vat-product-rates?filter[year]=2025');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'year', 'rate_type', 'rate'],
                ],
            ]);
        $this->assertGreaterThanOrEqual(25, count($response->json('data')));
    }

    public function test_endpoints_send_cache_headers(): void
    {
        $response = $this->getJson('/api/v1/tax/parameters?filter[year]=2025');
        $response->assertHeader('Cache-Control');
        $this->assertStringContainsString('s-maxage', $response->headers->get('Cache-Control'));
    }

    public function test_per_page_capped(): void
    {
        $response = $this->getJson('/api/v1/tax/parameters?per_page=999');
        $response->assertSuccessful();
        $this->assertLessThanOrEqual(200, count($response->json('data')));
    }
}
