<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\LotDTO;

class ProjectExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    public function extract(\SimpleXMLElement $project): LotDTO
    {
        $cbc = $project->children(self::NS_CBC);
        $cbcExt = $project->children(self::NS_CBC_EXT);
        $cac = $project->children(self::NS_CAC);

        $budget = $cac->BudgetAmount;
        $budgetCbc = $budget && $budget->count() ? $budget->children(self::NS_CBC) : null;

        $cpvCodes = [];
        foreach ($cac->RequiredCommodityClassification as $cpv) {
            $code = trim((string) $cpv->children(self::NS_CBC)->ItemClassificationCode);
            if ($code !== '') {
                $cpvCodes[] = $code;
            }
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

        $extOptions = null;
        $extension = $cac->ContractExtension;
        if ($extension && $extension->count()) {
            $extOptions = trim((string) $extension->children(self::NS_CBC)->OptionsDescription) ?: null;
        }

        return new LotDTO(
            lot_number: 1,
            title: trim((string) $cbc->Name) ?: null,
            description: null,
            tipo_contrato_code: trim((string) $cbc->TypeCode) ?: null,
            subtipo_contrato_code: trim((string) $cbcExt->SubTypeCode) ?: null,
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
            options_description: $extOptions,
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
