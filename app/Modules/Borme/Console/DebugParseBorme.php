<?php

namespace Modules\Borme\Console;

use Illuminate\Console\Command;
use Modules\Borme\Services\Parsers\SectionOneParser;

class DebugParseBorme extends Command
{
    protected $signature = 'borme:debug-parse
        {pdf : Absolute path to the BORME PDF file}
        {--type= : Filter by act type (constitution, appointment, ...)}
        {--entry= : Filter by entry number}';

    protected $description = 'Parse a local BORME PDF and dump extracted entries as JSON. No persistence.';

    public function handle(SectionOneParser $parser): int
    {
        $path = $this->argument('pdf');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Parsing: {$path}");
        $entries = $parser->parseFile($path);

        $type = $this->option('type');
        $entryFilter = $this->option('entry');

        $count = 0;
        foreach ($entries as $entry) {
            if ($entryFilter !== null && (string) $entry->entryNumber !== (string) $entryFilter) {
                continue;
            }

            if ($type !== null) {
                $present = array_map(fn ($t) => $t->value, $entry->actTypes);
                if (! in_array($type, $present, true)) {
                    continue;
                }
            }

            $this->line(json_encode($entry->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line(str_repeat('-', 80));
            $count++;
        }

        $this->info("Total entries dumped: {$count}");

        return self::SUCCESS;
    }
}
