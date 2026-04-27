<?php

namespace Modules\Borme\Services\Support;

class PageSplitter
{
    /**
     * Split BORME-A normalized text into per-entry blocks.
     *
     * Each entry begins with `^{number} - ` and ends right before the next entry
     * marker (or EOF). The trailing "Datos registrales. ... ({date})." line is
     * always the last sentence of the entry — we use that as a safety end anchor.
     *
     * @return array<int, array{number: int, raw: string}>
     */
    public function split(string $normalizedText): array
    {
        $entries = [];

        if (preg_match_all('/^(\d{5,})\s+-\s+/mu', $normalizedText, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $entries;
        }

        $offsets = $matches[0];
        $numbers = $matches[1];

        $count = count($offsets);
        for ($i = 0; $i < $count; $i++) {
            $start = $offsets[$i][1];
            $end = $i + 1 < $count ? $offsets[$i + 1][1] : strlen($normalizedText);
            $raw = trim(substr($normalizedText, $start, $end - $start));

            // Within an entry, newlines are PDF wrapping artefacts — flatten to
            // single spaces so downstream regex don't have to handle line breaks.
            $flat = preg_replace('/\s*\n\s*/u', ' ', $raw) ?? $raw;
            $flat = preg_replace('/\s{2,}/u', ' ', $flat) ?? $flat;

            $entries[] = [
                'number' => (int) $numbers[$i][0],
                'raw' => trim($flat),
            ];
        }

        return $entries;
    }
}
