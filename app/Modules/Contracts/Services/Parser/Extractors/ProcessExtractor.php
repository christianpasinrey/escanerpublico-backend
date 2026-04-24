<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\ProcessDTO;

class ProcessExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $process): ProcessDTO
    {
        $cbc = $process->children(self::NS_CBC);

        $docAvail = $process->children(self::NS_CAC)->DocumentAvailabilityPeriod;
        $fechaDispDocs = null;
        if ($docAvail && $docAvail->count()) {
            $fechaDispDocs = trim((string) $docAvail->children(self::NS_CBC)->EndDate) ?: null;
        }

        $deadline = $process->children(self::NS_CAC)->TenderSubmissionDeadlinePeriod;
        $fechaPresLim = null;
        $horaPresLim = null;
        if ($deadline && $deadline->count()) {
            $dlCbc = $deadline->children(self::NS_CBC);
            $fechaPresLim = trim((string) $dlCbc->EndDate) ?: null;
            $horaPresLim = trim((string) $dlCbc->EndTime) ?: null;
        }

        return new ProcessDTO(
            procedure_code: trim((string) $cbc->ProcedureCode) ?: null,
            urgency_code: trim((string) $cbc->UrgencyCode) ?: null,
            submission_method_code: trim((string) $cbc->SubmissionMethodCode) ?: null,
            contracting_system_code: trim((string) $cbc->ContractingSystemCode) ?: null,
            fecha_disponibilidad_docs: $fechaDispDocs,
            fecha_presentacion_limite: $fechaPresLim,
            hora_presentacion_limite: $horaPresLim,
        );
    }
}
