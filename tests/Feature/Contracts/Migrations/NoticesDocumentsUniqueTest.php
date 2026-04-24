<?php

namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NoticesDocumentsUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_notices_has_idempotency_unique(): void
    {
        $uniques = collect(Schema::getIndexes('contract_notices'))->where('unique', true)->pluck('columns')->all();
        $this->assertContains(['contract_id', 'notice_type_code', 'issue_date'], $uniques);
    }

    public function test_contract_documents_has_uri_unique(): void
    {
        $uniques = collect(Schema::getIndexes('contract_documents'))->where('unique', true)->pluck('columns')->all();
        $this->assertContains(['contract_id', 'uri'], $uniques);
    }
}
