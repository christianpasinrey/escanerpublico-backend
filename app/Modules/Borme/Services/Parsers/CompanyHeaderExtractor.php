<?php

namespace Modules\Borme\Services\Parsers;

use Modules\Borme\Services\Support\ActMarkerRegistry;
use Modules\Borme\Services\Support\LegalFormDetector;
use Modules\Borme\Services\Support\NameNormalizer;

class CompanyHeaderExtractor
{
    public function __construct(
        private readonly LegalFormDetector $legalForm,
        private readonly NameNormalizer $nameNormalizer,
    ) {}

    /**
     * Extract `{number} - {COMPANY NAME}` from an entry block. The name lives
     * between the entry prefix (`{number} - `) and the FIRST act marker in the
     * block. Lazy `.+?` regex don't work here because the rest of the entry can
     * contain `\.\s+(?=Marker)` sequences that backtrack into a far-away match;
     * we resolve this by locating the earliest marker offset directly.
     *
     * Returns ['number','name_raw','name_normalized','legal_form'] or null.
     */
    public function extract(string $entryBlock): ?array
    {
        if (! preg_match('/^(\d{5,})\s+-\s+/u', $entryBlock, $prefix, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $number = (int) $prefix[1][0];
        $afterPrefix = $prefix[0][1] + strlen($prefix[0][0]);

        $alt = ActMarkerRegistry::alternationForHeader();
        if (! preg_match('/'.$alt.'/u', $entryBlock, $marker, PREG_OFFSET_CAPTURE, $afterPrefix)) {
            return null;
        }

        $markerOffset = $marker[0][1];
        $rawName = rtrim(trim(substr($entryBlock, $afterPrefix, $markerOffset - $afterPrefix)), '.');

        $form = $this->legalForm->detect($rawName);
        $stripped = $form !== null ? $this->legalForm->stripSuffix($rawName) : $rawName;

        return [
            'number' => $number,
            'name_raw' => $rawName,
            'name_normalized' => $this->nameNormalizer->normalize($stripped),
            'legal_form' => $form,
        ];
    }
}
