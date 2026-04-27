<?php

namespace Modules\Borme\Services\Parsers;

use Modules\Borme\DTOs\OfficerDTO;
use Modules\Borme\Enums\OfficerAction;
use Modules\Borme\Enums\OfficerRole;
use Modules\Borme\Services\Support\ActMarkerRegistry;
use Modules\Borme\Services\Support\LegalFormDetector;
use Modules\Borme\Services\Support\NameNormalizer;

class OfficerExtractor
{
    /**
     * Action header → OfficerAction. Headers are matched as `Header.` literal
     * (period-terminated) inside the entry block.
     */
    private const ACTION_HEADERS = [
        'Nombramientos' => OfficerAction::Appointment,
        'Ceses/Dimisiones' => OfficerAction::Cease,
        'Reelecciones' => OfficerAction::Reelection,
        'Revocaciones' => OfficerAction::Revocation,
    ];

    /**
     * Role label (left of the colon) → OfficerRole. Order matters because some
     * labels are prefixes of others; the regex uses an alternation built in this
     * exact order so longer labels win first.
     */
    private const ROLES = [
        'Adm. Mancom.' => OfficerRole::JointAdmin,
        'Adm. Solid.' => OfficerRole::SeveralAdmin,
        'Adm. Unico' => OfficerRole::SoleAdmin,
        'Apo.Man.Soli' => OfficerRole::JointAttorney,
        'Apo.Manc.' => OfficerRole::JointAttorney,
        'Apo. Manc.' => OfficerRole::JointAttorney,
        'Apo.Sol.' => OfficerRole::SeveralAttorney,
        'Apo. Sol.' => OfficerRole::SeveralAttorney,
        'Apoderado' => OfficerRole::Attorney,
        'Consejero' => OfficerRole::BoardMember,
        'Presidente' => OfficerRole::ChairmanBoard,
        'Secretario' => OfficerRole::SecretaryBoard,
        'Liquidador' => OfficerRole::Liquidator,
    ];

    public function __construct(
        private readonly NameNormalizer $nameNormalizer,
        private readonly LegalFormDetector $legalFormDetector,
    ) {}

    /**
     * @return OfficerDTO[]
     */
    public function extract(string $entryBlock): array
    {
        $officers = [];

        foreach (self::ACTION_HEADERS as $header => $action) {
            $section = $this->extractSection($entryBlock, $header);
            if ($section === null) {
                continue;
            }

            $allLabels = array_merge(['Representan'], array_keys(self::ROLES));
            $alt = implode('|', array_map(fn ($l) => preg_quote($l, '/'), $allLabels));
            $pattern = '/('.$alt.'):\s*(.+?)(?=\s+(?:'.$alt.'):|\s*$)/su';

            if (preg_match_all($pattern, $section, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $m) {
                $label = $m[1];
                $value = trim(rtrim($m[2], '.'));

                if ($label === 'Representan') {
                    // Attaches to the most recently emitted officer (which must be
                    // a company-officer for it to make legal sense).
                    $last = end($officers);
                    if ($last !== false) {
                        $key = array_key_last($officers);
                        $officers[$key] = new OfficerDTO(
                            role: $last->role,
                            action: $last->action,
                            kind: $last->kind,
                            nameRaw: $last->nameRaw,
                            nameNormalized: $last->nameNormalized,
                            representativeNameRaw: $value,
                        );
                    }

                    continue;
                }

                $role = self::ROLES[$label] ?? OfficerRole::Other;
                $names = array_values(array_filter(array_map('trim', explode(';', $value))));

                foreach ($names as $name) {
                    $isCompany = $this->legalFormDetector->detect($name) !== null;
                    $kind = $isCompany ? 'company' : 'person';
                    $forNormalization = $isCompany ? $this->legalFormDetector->stripSuffix($name) : $name;
                    $officers[] = new OfficerDTO(
                        role: $role,
                        action: $action,
                        kind: $kind,
                        nameRaw: $name,
                        nameNormalized: $this->nameNormalizer->normalize($forNormalization),
                    );
                }
            }
        }

        return $officers;
    }

    private function extractSection(string $entryBlock, string $header): ?string
    {
        // Boundary alternation reuses the central ActMarkerRegistry so any
        // marker added to the catalogue automatically becomes a section
        // terminator here — avoids per-extractor drift.
        $alt = ActMarkerRegistry::alternation();
        $boundary = '/(?<![\p{L}])'.preg_quote($header, '/').'[.:]\s*(.+?)(?=(?:'.$alt.')[.:]|$)/su';

        if (preg_match($boundary, $entryBlock, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }
}
