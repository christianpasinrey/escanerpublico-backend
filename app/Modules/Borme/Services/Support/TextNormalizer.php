<?php

namespace Modules\Borme\Services\Support;

class TextNormalizer
{
    /**
     * Normalize raw PDF text for stable parsing:
     *  - NFC unicode form
     *  - replace common ligatures
     *  - drop soft-hyphens
     *  - strip page headers/footers (BORME boilerplate)
     *  - normalise whitespace (collapse multiple spaces, keep single newlines)
     */
    public function normalize(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($nfc !== false) {
                $text = $nfc;
            }
        }

        $text = strtr($text, [
            "\u{FB01}" => 'fi',
            "\u{FB02}" => 'fl',
            "\u{00AD}" => '',         // soft hyphen
            "\u{00A0}" => ' ',        // nbsp
            "\u{2009}" => ' ',        // thin space
            "\u{200B}" => '',         // zero-width space
            "\r\n" => "\n",
            "\r" => "\n",
        ]);

        $text = preg_replace(
            '/^BOLETÍN OFICIAL DEL REGISTRO MERCANTIL\nNúm\.\s*\d+\s+\S+\s+\d+\s+de\s+\S+\s+de\s+\d+\s+Pág\.\s+\d+\ncve:\s*BORME-[A-Z]-\d+-\d+-\d+\nVerificable en https:\/\/www\.boe\.es\n?/mu',
            "\n",
            $text
        ) ?? $text;

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        $text = preg_replace('/\n{2,}/', "\n", $text) ?? $text;

        return trim($text);
    }
}
