<?php

namespace Modules\Borme\Services\Support;

class DateParser
{
    /**
     * BORME uses DD.MM.YY two-digit years. Hardcoded to 21st century — BORME
     * data is digitised from 2009 onwards, so any "YY" in this corpus belongs
     * to 20YY. Returns ISO string `YYYY-MM-DD` or null when unparseable.
     */
    public function parseShort(string $raw): ?string
    {
        $raw = trim($raw);
        if (! preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $raw, $m)) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = 2000 + (int) $m[3];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
