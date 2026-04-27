<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;

class DisolucionParser implements ActParserInterface
{
    /** Known causes that follow `Disolución.` in BORME-A entries. */
    private const CAUSES = [
        'Voluntaria' => 'voluntary',
        'Fusion' => 'merger',
        'Fusión' => 'merger',
        'Pleno derecho' => 'by_law',
        'Pérdidas' => 'losses',
        'Concursal' => 'concurso',
        'Judicial' => 'judicial',
    ];

    public function supports(): ActType
    {
        return ActType::Dissolution;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        if (! preg_match('/Disolución\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su', $entryBlock, $m)) {
            return null;
        }

        $section = trim(rtrim($m[1], '.'));
        $cause = null;
        $causeRaw = null;

        foreach (self::CAUSES as $label => $code) {
            if (preg_match('/(?<![\p{L}])'.preg_quote($label, '/').'(?![\p{L}])/u', $section) === 1) {
                $cause = $code;
                $causeRaw = $label;
                break;
            }
        }

        return new ActItemDTO(
            actType: ActType::Dissolution,
            payload: ['cause' => $cause, 'cause_raw' => $causeRaw, 'description' => $section],
        );
    }
}
