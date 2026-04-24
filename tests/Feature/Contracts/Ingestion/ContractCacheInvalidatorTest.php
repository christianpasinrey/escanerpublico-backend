<?php

namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Services\Cache\ContractCacheInvalidator;
use Tests\TestCase;

class ContractCacheInvalidatorTest extends TestCase
{
    public function test_flushes_contract_tag(): void
    {
        Cache::tags(['contract:42'])->put('payload', 'abc', 300);
        $this->assertEquals('abc', Cache::tags(['contract:42'])->get('payload'));

        app(ContractCacheInvalidator::class)->invalidateContract(42);

        $this->assertNull(Cache::tags(['contract:42'])->get('payload'));
    }

    public function test_flushes_org_and_company_tags(): void
    {
        Cache::tags(['org:1'])->put('a', 1, 300);
        Cache::tags(['company:7'])->put('b', 2, 300);

        app(ContractCacheInvalidator::class)->invalidateOrganization(1);
        app(ContractCacheInvalidator::class)->invalidateCompany(7);

        $this->assertNull(Cache::tags(['org:1'])->get('a'));
        $this->assertNull(Cache::tags(['company:7'])->get('b'));
    }
}
