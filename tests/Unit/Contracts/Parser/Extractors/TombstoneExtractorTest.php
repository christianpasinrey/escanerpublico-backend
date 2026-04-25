<?php

namespace Tests\Unit\Contracts\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\Extractors\TombstoneExtractor;
use Tests\TestCase;

class TombstoneExtractorTest extends TestCase
{
    public function test_extracts_ref_and_when(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-06-tombstone.xml'));
        $feed = new \SimpleXMLElement($xml);
        $atNs = 'http://purl.org/atompub/tombstones/1.0';
        $deleted = $feed->children($atNs)->{'deleted-entry'};

        $dto = (new TombstoneExtractor)->extract($deleted[0]);

        $this->assertInstanceOf(TombstoneDTO::class, $dto);
        $this->assertStringContainsString('/19163035', $dto->ref);
        $this->assertEquals('2026-03-20', $dto->when->format('Y-m-d'));
    }
}
