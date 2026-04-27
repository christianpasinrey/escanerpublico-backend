<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;
use Modules\Borme\Services\Support\CurrencyParser;

class AmpliacionCapitalParser implements ActParserInterface
{
    public function __construct(private readonly CurrencyParser $currencyParser) {}

    public function supports(): ActType
    {
        return ActType::CapitalIncrease;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $section = $this->extractSection($entryBlock);
        if ($section === null) {
            return null;
        }

        $payload = [
            'capital_after' => $this->extractCapital($section, 'Capital'),
            'subscribed' => $this->extractCapital($section, 'Resultante Suscrito'),
            'paid_in' => $this->extractCapital($section, 'Resultante Desembolsado'),
        ];

        return new ActItemDTO(actType: ActType::CapitalIncrease, payload: $payload);
    }

    private function extractSection(string $entryBlock): ?string
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/Ampliación de capital\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        return trim($m[1]);
    }

    private function extractCapital(string $section, string $label): ?array
    {
        if (! preg_match('/'.preg_quote($label, '/').':\s*(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?\s*(?:Euros?|€))/iu', $section, $m)) {
            return null;
        }

        return $this->currencyParser->parse($m[1]);
    }
}
