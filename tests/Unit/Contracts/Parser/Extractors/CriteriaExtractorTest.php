<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\CriteriaExtractor;
use Tests\TestCase;

class CriteriaExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_criteria_with_types_and_weights(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-09-with-criteria.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $terms = $folder->children(self::NS_CAC)->TenderingTerms;

        $result = (new CriteriaExtractor())->extract($terms, defaultLotNumber: 1);

        $this->assertArrayHasKey(1, $result);
        $this->assertGreaterThanOrEqual(2, count($result[1]));
        foreach ($result[1] as $i => $c) {
            $this->assertEquals($i + 1, $c->sort_order);
            $this->assertContains($c->type_code, ['OBJ', 'SUBJ']);
            $this->assertNotEmpty($c->description);
        }
    }
}
