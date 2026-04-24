<?php

namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Http\Resources\ContractResource;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ResourceStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_resource_base_fields(): void
    {
        $c = Contract::factory()->create();
        $arr = (new ContractResource($c))->toArray(request());

        foreach (['id', 'external_id', 'expediente', 'objeto', 'status_code', 'importe_con_iva', 'snapshot_updated_at'] as $k) {
            $this->assertArrayHasKey($k, $arr);
        }
        // After JSON serialization, MissingValue entries get stripped by Laravel.
        // Decode the resource response to verify that unloaded relations aren't leaked.
        $response = (new ContractResource($c))->response(request());
        $serialized = json_decode($response->getContent(), true);
        $this->assertArrayNotHasKey('lots', $serialized['data']);
    }

    public function test_contract_resource_includes_lots_when_loaded(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create();
        $c->load('lots');

        $arr = (new ContractResource($c))->toArray(request());
        $this->assertArrayHasKey('lots', $arr);
        $this->assertCount(1, $arr['lots']);
    }
}
