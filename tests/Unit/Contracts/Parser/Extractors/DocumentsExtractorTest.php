<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\DocumentsExtractor;
use Tests\TestCase;

class DocumentsExtractorTest extends TestCase
{
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_all_document_types(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;

        $docs = (new DocumentsExtractor)->extract($folder);

        $this->assertNotEmpty($docs);
        foreach ($docs as $d) {
            $this->assertContains($d->type, ['legal', 'technical', 'additional', 'general']);
            $this->assertNotEmpty($d->name);
        }
    }
}
