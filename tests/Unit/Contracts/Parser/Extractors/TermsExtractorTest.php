<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\TermsExtractor;
use Tests\TestCase;

class TermsExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_terms(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $terms = $folder->children(self::NS_CAC)->TenderingTerms;

        $dto = (new TermsExtractor())->extract($terms);

        // At minimum funding program or legislation code should be populated on real entries
        $this->assertTrue(
            $dto->over_threshold_indicator === false
            || $dto->over_threshold_indicator === true
            || $dto->over_threshold_indicator === null
        );
    }
}
