<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;

class CambioObjetoParser implements ActParserInterface
{
    public function supports(): ActType
    {
        return ActType::ObjectChange;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/(?:Cambio de objeto social|Ampliación del objeto social)\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        return new ActItemDTO(
            actType: ActType::ObjectChange,
            payload: ['new_object' => trim($m[1])],
        );
    }
}
