<?php

namespace Modules\Borme\Services\Parsers;

use Modules\Borme\Services\Support\DateParser;

class RegistryDataExtractor
{
    public function __construct(private readonly DateParser $dateParser) {}

    /**
     * Parse the "Datos registrales. S X , H L NNNNNN, I/A NN (DD.MM.YY)." sentence.
     * Returns ['letter','sheet','section','inscription','date'] (date as ISO YYYY-MM-DD)
     * or null when the sentence is absent.
     */
    public function extract(string $entryBlock): ?array
    {
        // Optional `T <num> , F <num> ,` (Tomo + Folio) appears on Madrid sheets
        // for first-inscription entries — accept and ignore.
        if (! preg_match(
            '/Datos registrales\.\s*(?:T\s*\d+\s*,\s*F\s*\d+\s*,\s*)?S\s*(\d+)\s*,\s*H\s*([A-Z]{1,2})\s*(\d+)\s*,\s*([^()]+?)\s*\(\s*(\d{1,2}\.\d{1,2}\.\d{2})\s*\)\.?/u',
            $entryBlock,
            $m
        )) {
            return null;
        }

        return [
            'section' => trim($m[1]),
            'letter' => $m[2],
            'sheet' => (int) $m[3],
            'inscription' => trim($m[4]),
            'date' => $this->dateParser->parseShort($m[5]),
        ];
    }
}
