<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;

class CambioDomicilioParser implements ActParserInterface
{
    public function supports(): ActType
    {
        return ActType::AddressChange;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/Cambio de domicilio social\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        $section = trim($m[1]);

        // Format: "{ADDRESS} ({CITY})." — same shape as Constitución->Domicilio.
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\.?$/u', $section, $am)) {
            return new ActItemDTO(
                actType: ActType::AddressChange,
                payload: [
                    'new_address' => trim($am[1]),
                    'new_city' => trim($am[2]),
                ],
            );
        }

        return new ActItemDTO(
            actType: ActType::AddressChange,
            payload: ['new_address' => $section, 'new_city' => null],
        );
    }
}
