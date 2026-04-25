<?php

namespace Tests\Unit\Contracts\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\PlacspEntryParser;
use Tests\TestCase;

class PlacspEntryParserTest extends TestCase
{
    public function test_parses_full_entry_from_fixture(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-02-adj.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];

        $parser = app(PlacspEntryParser::class);
        $dto = $parser->parse($entry);

        $this->assertInstanceOf(EntryDTO::class, $dto);
        $this->assertNotEmpty($dto->external_id);
        $this->assertNotEmpty($dto->expediente);
        $this->assertEquals('ADJ', $dto->status_code);
        $this->assertNotNull($dto->organization);
        $this->assertGreaterThanOrEqual(1, count($dto->lots));
        $this->assertGreaterThanOrEqual(1, count($dto->results));
    }

    public function test_falls_back_to_project_when_no_lots(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/placsp/sample-01-pub.xml'));
        $feed = new \SimpleXMLElement($xml);
        $entry = $feed->entry[0];

        $dto = app(PlacspEntryParser::class)->parse($entry);

        $this->assertCount(1, $dto->lots);
        $this->assertEquals(1, $dto->lots[0]->lot_number);
    }
}
