<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractLotsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_lots_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_lots'));
        $cols = ['id','contract_id','lot_number','title','description',
            'tipo_contrato_code','subtipo_contrato_code','cpv_codes',
            'budget_with_tax','budget_without_tax','estimated_value',
            'duration','duration_unit','start_date','end_date',
            'nuts_code','lugar_ejecucion','options_description',
            'created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_lots', $c), "Missing col {$c}");
        }
    }

    public function test_contract_lots_unique_contract_and_lot_number(): void
    {
        $uniques = collect(Schema::getIndexes('contract_lots'))
            ->where('unique', true)
            ->pluck('columns')
            ->all();
        $this->assertContains(['contract_id','lot_number'], $uniques);
    }
}
