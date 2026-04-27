<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;

class CesesParser implements ActParserInterface
{
    public function supports(): ActType
    {
        return ActType::Cease;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/(?<![\p{L}])Ceses\/Dimisiones\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        return new ActItemDTO(
            actType: ActType::Cease,
            payload: ['summary' => trim(rtrim($m[1], '.'))],
        );
    }
}
