<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;

/**
 * Surfaces a structured summary of an entry's appointment block as an act_item.
 * The granular officer rows live in `borme_officers` (canonical source); this
 * payload exists so the /companies UI can show "appointed N people" without
 * joining the officers table for the listing view.
 */
class NombramientosParser implements ActParserInterface
{
    public function supports(): ActType
    {
        return ActType::Appointment;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/(?<![\p{L}])Nombramientos\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        $section = trim(rtrim($m[1], '.'));

        return new ActItemDTO(
            actType: ActType::Appointment,
            payload: ['summary' => $section],
        );
    }
}
