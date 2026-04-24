<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\TermsDTO;

class TermsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $terms): TermsDTO
    {
        $cbc = $terms->children(self::NS_CBC);

        $language = null;
        $langEl = $terms->children(self::NS_CAC)->Language;
        if ($langEl && $langEl->count()) {
            $language = trim((string) $langEl->children(self::NS_CBC)->ID) ?: null;
        }

        $guaranteeTypeCode = null;
        $guaranteePct = null;
        $guarantee = $terms->children(self::NS_CAC)->RequiredFinancialGuarantee;
        if ($guarantee && $guarantee->count()) {
            $guaranteeTypeCode = trim((string) $guarantee->children(self::NS_CBC)->GuaranteeTypeCode) ?: null;
            $rate = trim((string) $guarantee->children(self::NS_CBC)->AmountRate);
            if ($rate !== '') {
                $guaranteePct = (float) $rate;
            }
        }

        $over = trim((string) $cbc->OverThresholdIndicator);
        $variant = trim((string) $cbc->VariantConstraintIndicator);
        $curricula = trim((string) $cbc->RequiredCurriculaIndicator);
        $appeals = trim((string) $cbc->ReceivedAppealQuantity);

        return new TermsDTO(
            language: $language,
            funding_program_code: trim((string) $cbc->FundingProgramCode) ?: null,
            national_legislation_code: trim((string) $cbc->ProcurementNationalLegislationCode) ?: null,
            over_threshold_indicator: $over === '' ? null : ($over === 'true'),
            received_appeal_quantity: $appeals === '' ? null : (int) $appeals,
            variant_constraint_indicator: $variant === '' ? null : ($variant === 'true'),
            required_curricula_indicator: $curricula === '' ? null : ($curricula === 'true'),
            guarantee_type_code: $guaranteeTypeCode,
            guarantee_percentage: $guaranteePct,
        );
    }
}
