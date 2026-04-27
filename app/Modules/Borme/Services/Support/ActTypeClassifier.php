<?php

namespace Modules\Borme\Services\Support;

use Modules\Borme\Enums\ActType;

class ActTypeClassifier
{
    /**
     * @return ActType[]
     */
    public function classify(string $entryBlock): array
    {
        $found = [];
        foreach (ActMarkerRegistry::markers() as $marker => $type) {
            // Skip the "Datos registrales" sentinel — it is not an act in itself.
            if ($type === ActType::Other && $marker === 'Datos registrales') {
                continue;
            }

            // BORME terminates marker lines with `.` for most acts, but with `:`
            // for free-form entries like "Fe de erratas:" or "Otros conceptos:".
            if (preg_match('/(?<![\p{L}])'.preg_quote($marker, '/').'[.:]/u', $entryBlock) === 1) {
                $found[$type->value] = $type;
            }
        }

        return array_values($found);
    }
}
