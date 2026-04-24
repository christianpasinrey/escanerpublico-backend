<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\Extractors\ProcessExtractor;
use Tests\TestCase;

class ProcessExtractorTest extends TestCase
{
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function test_extracts_process_codes_and_dates(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $folder = $feed->entry[0]->children(self::NS_CAC_EXT)->ContractFolderStatus;
        $process = $folder->children(self::NS_CAC)->TenderingProcess;

        $dto = (new ProcessExtractor)->extract($process);

        $this->assertNotNull($dto->procedure_code);
    }
}
