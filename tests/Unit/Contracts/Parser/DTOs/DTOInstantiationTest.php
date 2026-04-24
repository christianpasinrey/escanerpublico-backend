<?php

namespace Tests\Unit\Contracts\Parser\DTOs;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;
use Tests\TestCase;

class DTOInstantiationTest extends TestCase
{
    public function test_entry_dto_instantiable(): void
    {
        $org = new OrganizationDTO(
            name: 'Ayto. Trujillo',
            dir3: 'L01101954',
            nif: 'P1019900H',
            platform_id: null,
            buyer_profile_uri: null,
            activity_code: '1',
            type_code: '3',
            hierarchy: [],
            address: null,
            contacts: [],
        );

        $entry = new EntryDTO(
            external_id: 'https://x/1',
            link: null,
            expediente: 'TEST/1',
            status_code: 'PUB',
            entry_updated_at: new \DateTimeImmutable('2026-03-20T19:06:57+01:00'),
            organization: $org,
            lots: [],
            process: null,
            results: [],
            terms: null,
            criteria_by_lot: [],
            notices: [],
            documents: [],
        );

        $this->assertEquals('TEST/1', $entry->expediente);
        $this->assertEquals('Ayto. Trujillo', $entry->organization->name);
    }
}
