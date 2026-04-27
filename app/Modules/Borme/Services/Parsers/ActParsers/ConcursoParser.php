<?php

namespace Modules\Borme\Services\Parsers\ActParsers;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;
use Modules\Borme\Services\Support\ActMarkerRegistry;
use Modules\Borme\Services\Support\DateParser;

class ConcursoParser implements ActParserInterface
{
    public function __construct(private readonly DateParser $dateParser) {}

    public function supports(): ActType
    {
        return ActType::Concurso;
    }

    public function parse(string $entryBlock): ?ActItemDTO
    {
        $alt = ActMarkerRegistry::alternation();
        $patterns = [
            '/Situación concursal\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su',
            '/Concurso\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su',
            '/Cierre provisional hoja registral\.\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su',
        ];

        $section = null;
        foreach ($patterns as $p) {
            if (preg_match($p, $entryBlock, $m) === 1) {
                $section = trim($m[1]);
                break;
            }
        }
        if ($section === null) {
            return null;
        }

        $procedure = null;
        if (preg_match('/Procedimiento concursal\s+([\d\/]+)/u', $section, $pm)) {
            $procedure = trim($pm[1]);
        }

        $firme = null;
        if (preg_match('/FIRME:\s*(Si|No|Sí)/iu', $section, $fm)) {
            $firme = strcasecmp($fm[1], 'No') !== 0;
        }

        $resolutionDate = null;
        if (preg_match('/Fecha de resoluci[oó]n\s+([\d\/.\-]+)/u', $section, $dm)) {
            $resolutionDate = $this->normaliseDate($dm[1]);
        }

        $juzgado = null;
        if (preg_match('/Juzgado:\s*([^.]+)/u', $section, $jm)) {
            $juzgado = trim($jm[1]);
        }

        return new ActItemDTO(
            actType: ActType::Concurso,
            payload: [
                'procedure_number' => $procedure,
                'firme' => $firme,
                'resolution_date' => $resolutionDate,
                'juzgado' => $juzgado,
                'description' => $section,
            ],
            effectiveDate: $resolutionDate,
        );
    }

    private function normaliseDate(string $raw): ?string
    {
        $raw = str_replace(['/', '.'], '-', trim($raw));
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2,4})$/', $raw, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }

            return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
        }

        return null;
    }
}
