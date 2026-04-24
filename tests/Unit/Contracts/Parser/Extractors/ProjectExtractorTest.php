<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ProjectExtractor;
use Tests\TestCase;

class ProjectExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_project_into_single_lot_dto(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lot = (new ProjectExtractor())->extract($project);

        $this->assertEquals(1, $lot->lot_number);
        $this->assertNotEmpty($lot->title);
        $this->assertNotNull($lot->budget_with_tax);
        $this->assertIsArray($lot->cpv_codes);
    }
}
