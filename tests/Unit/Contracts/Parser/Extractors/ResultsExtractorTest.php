<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ResultsExtractor;
use Tests\TestCase;

class ResultsExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_single_result_adj(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(1, count($results));
        $r = $results[0];
        $this->assertNotNull($r->winner);
        $this->assertNotNull($r->winner->address);
        $this->assertNotNull($r->amount_with_tax);
    }

    public function test_extracts_multiple_results_multi_lote(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-04-multi-lote.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(2, count($results));
    }

    public function test_handles_no_winner(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-07-sin-winner.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $results = (new ResultsExtractor())->extract($folder);

        foreach ($results as $r) {
            $this->assertNull($r->winner);
        }
    }
}
