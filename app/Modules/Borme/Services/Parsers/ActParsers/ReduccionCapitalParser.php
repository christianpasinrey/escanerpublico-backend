<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;
use Modules\Borme\Services\Support\CurrencyParser;

class ReduccionCapitalParser implements ActParserInterface
{
    public function __construct(private readonly CurrencyParser $currencyParser) {}

    public function supports(): ActType
    {
        return ActType::CapitalDecrease;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/Reducción de capital\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        $section = trim($m[1]);
        $capital = null;
        if (preg_match('/Capital:\s*(\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?\s*(?:Euros?|€))/iu', $section, $cm)) {
            $capital = $this->currencyParser->parse($cm[1]);
        }

        return new ActItemDTO(
            actType: ActType::CapitalDecrease,
            payload: ['capital_after' => $capital],
        );
    }
}
