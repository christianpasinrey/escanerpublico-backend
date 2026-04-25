<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\LotDTO;

class LotsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    /** @return LotDTO[] */
    public function extract(\SimpleXMLElement $project): array
    {
        // ProcurementProjectLot elements are siblings of the given project (both under ContractFolderStatus).
        // Use xpath to reach them.
        $project->registerXPathNamespace('cac', self::NS_CAC);
        $lotNodes = $project->xpath('../cac:ProcurementProjectLot') ?: [];

        $lots = [];
        $i = 1;

        foreach ($lotNodes as $lotNode) {
            $lots[] = $this->parseLot($lotNode, $i++);
        }

        return $lots;
    }

    private function parseLot(\SimpleXMLElement $lotNode, int $defaultNum): LotDTO
    {
        $lotCbc = $lotNode->children(self::NS_CBC);
        $lotCac = $lotNode->children(self::NS_CAC);

        $lotNumberStr = trim((string) $lotCbc->ID);
        $lotNumber = ctype_digit($lotNumberStr) ? (int) $lotNumberStr : $defaultNum;

        // Inner ProcurementProject inside ProcurementProjectLot carries title/budget/cpv/etc.
        $innerProject = $lotCac->ProcurementProject;
        $hasInner = $innerProject && $innerProject->count();
        $cbc = $hasInner ? $innerProject->children(self::NS_CBC) : $lotCbc;
        $cac = $hasInner ? $innerProject->children(self::NS_CAC) : $lotCac;

        $budget = $cac->BudgetAmount;
        $budgetCbc = $budget && $budget->count() ? $budget->children(self::NS_CBC) : null;

        $cpvCodes = [];
        foreach ($cac->RequiredCommodityClassification as $cpv) {
            $code = trim((string) $cpv->children(self::NS_CBC)->ItemClassificationCode);
            if ($code !== '') {
                $cpvCodes[] = $code;
            }
        }

        $period = $cac->PlannedPeriod;
        $duration = null;
        $durationUnit = null;
        $startDate = null;
        $endDate = null;
        if ($period && $period->count()) {
            $durEl = $period->children(self::NS_CBC)->DurationMeasure;
            $durVal = trim((string) $durEl);
            if ($durVal !== '') {
                $duration = (float) $durVal;
                $durationUnit = (string) ($durEl->attributes()->unitCode ?? 'MON');
            }
            $startDate = trim((string) $period->children(self::NS_CBC)->StartDate) ?: null;
            $endDate = trim((string) $period->children(self::NS_CBC)->EndDate) ?: null;
        }

        $location = $cac->RealizedLocation;
        $nutsCode = null;
        $lugarEjec = null;
        if ($location && $location->count()) {
            $nutsCode = trim((string) $location->children(self::NS_CBC)->CountrySubentityCode) ?: null;
            $address = $location->children(self::NS_CAC)->Address;
            if ($address && $address->count()) {
                $lugarEjec = trim((string) $address->children(self::NS_CBC)->CityName) ?: null;
            }
        }

        $cbcExtSource = $hasInner ? $innerProject->children(self::NS_CBC_EXT) : $lotNode->children(self::NS_CBC_EXT);

        return new LotDTO(
            lot_number: $lotNumber,
            title: trim((string) $cbc->Name) ?: null,
            description: trim((string) $cbc->Description) ?: null,
            tipo_contrato_code: trim((string) $cbc->TypeCode) ?: null,
            subtipo_contrato_code: trim((string) $cbcExtSource->SubTypeCode) ?: null,
            cpv_codes: $cpvCodes,
            budget_with_tax: $budgetCbc ? $this->decimal($budgetCbc->TotalAmount) : null,
            budget_without_tax: $budgetCbc ? $this->decimal($budgetCbc->TaxExclusiveAmount) : null,
            estimated_value: $budgetCbc ? $this->decimal($budgetCbc->EstimatedOverallContractAmount) : null,
            duration: $duration,
            duration_unit: $durationUnit,
            start_date: $startDate,
            end_date: $endDate,
            nuts_code: $nutsCode,
            lugar_ejecucion: $lugarEjec,
            options_description: null,
        );
    }

    private function decimal(?\SimpleXMLElement $el): ?float
    {
        if ($el === null) {
            return null;
        }
        $v = trim((string) $el);

        return $v !== '' ? (float) $v : null;
    }
}
