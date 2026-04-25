<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\CriterionDTO;

class CriteriaExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    /** @return array<int, CriterionDTO[]> */
    public function extract(\SimpleXMLElement $terms, int $defaultLotNumber = 1): array
    {
        $awardingTerms = $terms->children(self::NS_CAC)->AwardingTerms;
        if (! $awardingTerms || ! $awardingTerms->count()) {
            return [];
        }

        $criteriaByLot = [];
        $sortOrder = 1;

        foreach ($awardingTerms->children(self::NS_CAC)->AwardingCriteria as $criteria) {
            $cbc = $criteria->children(self::NS_CBC);

            $desc = trim((string) $cbc->Description);
            if ($desc === '') {
                continue;
            }

            $typeCode = trim((string) $cbc->AwardingCriteriaTypeCode) ?: 'OBJ';
            $subtypeCode = trim((string) $cbc->AwardingCriteriaSubTypeCode) ?: null;
            $note = trim((string) $cbc->Note) ?: null;
            $weightStr = trim((string) $cbc->WeightNumeric);
            $weight = $weightStr === '' ? null : (float) $weightStr;

            // Lot reference — if present, attribute to that lot; else default lot
            $lotRefStr = trim((string) $cbc->ProcurementProjectLotID);
            $lotNumber = ctype_digit($lotRefStr) ? (int) $lotRefStr : $defaultLotNumber;

            $criteriaByLot[$lotNumber][] = new CriterionDTO(
                lot_number: $lotNumber,
                type_code: $typeCode,
                subtype_code: $subtypeCode,
                description: $desc,
                note: $note,
                weight_numeric: $weight,
                sort_order: $sortOrder++,
            );
        }

        return $criteriaByLot;
    }
}
