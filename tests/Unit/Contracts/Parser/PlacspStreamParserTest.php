<?php

namespace Tests\Unit\Contracts\Parser;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class PlacspStreamParserTest extends TestCase
{
    public function test_streams_entries_and_tombstones(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = [];
        $tombstones = [];

        foreach ($parser->stream(base_path('tests/Fixtures/placsp/full-20-entries.atom')) as $item) {
            if ($item instanceof EntryDTO) {
                $entries[] = $item;
            }
            if ($item instanceof TombstoneDTO) {
                $tombstones[] = $item;
            }
        }

        $this->assertGreaterThanOrEqual(20, count($entries));
    }

    public function test_memory_bounded_on_large_file(): void
    {
        $parser = app(PlacspStreamParser::class);
        $before = memory_get_usage(true);

        foreach ($parser->stream(base_path('tests/Fixtures/placsp/full-20-entries.atom')) as $_) {
            // consume
        }

        $peak = memory_get_peak_usage(true);
        $this->assertLessThan(50 * 1024 * 1024, $peak - $before, 'Memory grew beyond 50MB');
    }
}
