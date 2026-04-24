<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;
use Modules\Contracts\Services\Parser\Extractors\OrganizationExtractor;
use Tests\TestCase;

class OrganizationExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_full_organization(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];
        $folder = $entry->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $locatedParty = $folder->children(self::NS_CAC_EXT)->LocatedContractingParty;

        $dto = (new OrganizationExtractor)->extract($locatedParty);

        $this->assertInstanceOf(OrganizationDTO::class, $dto);
        $this->assertNotEmpty($dto->name);
        $this->assertMatchesRegularExpression('/^[A-Z]\d+/', $dto->dir3 ?? '');
        $this->assertStringStartsWith('P', $dto->nif ?? '');
        $this->assertNotEmpty($dto->hierarchy);
        $this->assertNotNull($dto->address);
    }

    public function test_handles_missing_optional_fields(): void
    {
        $xmlString = '<?xml version="1.0"?>
<root xmlns:cbc="urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2"
      xmlns:cac="urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2"
      xmlns:cac-place-ext="urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2">
    <cac-place-ext:LocatedContractingParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>Nombre Mínimo</cbc:Name></cac:PartyName>
        </cac:Party>
    </cac-place-ext:LocatedContractingParty>
</root>';
        $root = new \SimpleXMLElement($xmlString);
        $lp = $root->children(self::NS_CAC_EXT)->LocatedContractingParty;

        $dto = (new OrganizationExtractor)->extract($lp);

        $this->assertEquals('Nombre Mínimo', $dto->name);
        $this->assertNull($dto->dir3);
        $this->assertNull($dto->nif);
        $this->assertEquals([], $dto->hierarchy);
    }
}
