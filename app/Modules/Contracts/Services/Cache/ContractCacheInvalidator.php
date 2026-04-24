<?php

namespace Modules\Contracts\Services\Cache;

use Illuminate\Support\Facades\Cache;

class ContractCacheInvalidator
{
    public function invalidateContract(int $id): void
    {
        Cache::tags(['contract:'.$id])->flush();
    }

    public function invalidateOrganization(int $id): void
    {
        Cache::tags(['org:'.$id])->flush();
    }

    public function invalidateCompany(int $id): void
    {
        Cache::tags(['company:'.$id])->flush();
    }

    public function invalidateListings(): void
    {
        Cache::tags(['contracts:list'])->flush();
    }
}
