<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\LotsExtractor;
use Tests\TestCase;

class LotsExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_multiple_lots(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-04-multi-lote.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lots = (new LotsExtractor())->extract($project);

        $this->assertGreaterThanOrEqual(2, count($lots));
        foreach ($lots as $i => $lot) {
            $this->assertEquals($i + 1, $lot->lot_number);
        }
    }

    public function test_returns_empty_when_no_lots_element(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $project = $folder->children(self::NS_CAC)->ProcurementProject;

        $lots = (new LotsExtractor())->extract($project);

        $this->assertEquals([], $lots);
    }
}
