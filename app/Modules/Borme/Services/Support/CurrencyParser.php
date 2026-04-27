<?php

namespace Modules\Borme\Services\Support;

class CurrencyParser
{
    /**
     * Parse Spanish-format currency like "3.000,00 Euros" / "1,00 Euros" / "1.234,56 €".
     * Returns ['amount_cents' => int, 'currency' => 'EUR'] or null.
     */
    public function parse(string $raw): ?array
    {
        $raw = trim($raw);

        if (! preg_match('/(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?)\s*(Euros?|€|pesetas?|Ptas?\.?)/iu', $raw, $m)) {
            return null;
        }

        $number = str_replace('.', '', $m[1]);
        $number = str_replace(',', '.', $number);
        $float = (float) $number;

        $unit = mb_strtolower($m[2]);
        $currency = str_starts_with($unit, 'euro') || $m[2] === '€' ? 'EUR' : 'ESP';

        if ($currency === 'ESP') {
            $float = $float / 166.386;
            $currency = 'EUR';
        }

        return [
            'amount_cents' => (int) round($float * 100),
            'currency' => $currency,
        ];
    }
}
