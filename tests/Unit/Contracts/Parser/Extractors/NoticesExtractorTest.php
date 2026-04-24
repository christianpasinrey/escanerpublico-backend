<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\NoticesExtractor;
use Tests\TestCase;

class NoticesExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_all_notice_types(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-03-res-formalized.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $notices = (new NoticesExtractor())->extract($folder);

        $this->assertGreaterThanOrEqual(3, count($notices));
        foreach ($notices as $n) {
            $this->assertNotEmpty($n->notice_type_code);
            $this->assertNotEmpty($n->issue_date);
        }

        $types = array_map(fn ($n) => $n->notice_type_code, $notices);
        $this->assertContains('DOC_CAN_ADJ', $types);
    }
}
