<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\CurrencyParser;
use Modules\Borme\Services\Support\DateParser;

class ConstitucionParser implements ActParserInterface
{
    public function __construct(
        private readonly CurrencyParser $currencyParser,
        private readonly DateParser $dateParser,
    ) {}

    public function supports(): ActType
    {
        return ActType::Constitution;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $section = $this->extractSection($entryBlock);
        if ($section === null) {
            return null;
        }

        $payload = [
            'start_date' => $this->parseStartDate($section),
            'object' => $this->parseObject($section),
            'domicile' => $this->parseDomicile($section),
            'capital' => $this->parseCapital($section),
        ];

        return new ActItemDTO(
            actType: ActType::Constitution,
            payload: $payload,
            effectiveDate: $payload['start_date'],
        );
    }

    private function extractSection(string $entryBlock): ?string
    {
        if (! preg_match(
            '/Constitución\.\s*(.+?)(?=(?:Declaración de unipersonalidad|Pérdida del carácter de unipersonalidad|Sociedad unipersonal|Nombramientos|Ceses\/Dimisiones|Reelecciones|Revocaciones|Datos registrales)\.)/su',
            $entryBlock,
            $m
        )) {
            return null;
        }

        return trim($m[1]);
    }

    private function parseStartDate(string $section): ?string
    {
        if (! preg_match('/Comienzo de operaciones:\s*(\d{1,2}\.\d{1,2}\.\d{2})/u', $section, $m)) {
            return null;
        }

        return $this->dateParser->parseShort($m[1]);
    }

    private function parseObject(string $section): ?string
    {
        if (! preg_match('/Objeto social:\s*(.+?)(?=\.\s+(?:Domicilio|Capital):)/su', $section, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    private function parseDomicile(string $section): ?array
    {
        if (! preg_match('/Domicilio:\s*(.+?)\s*\(([^)]+)\)\./u', $section, $m)) {
            return null;
        }

        return [
            'address' => trim($m[1]),
            'city' => trim($m[2]),
        ];
    }

    private function parseCapital(string $section): ?array
    {
        if (! preg_match('/Capital:\s*(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?\s*(?:Euros?|€|pesetas?|Ptas?\.?))/iu', $section, $m)) {
            return null;
        }

        return $this->currencyParser->parse($m[1]) ?? ['raw' => trim($m[1])];
    }
}
