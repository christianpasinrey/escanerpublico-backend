<?php

namespace App\Services;

use SimpleXMLElement;

class PlacspParser
{
    /**
     * Parsea un fichero ATOM de la PLACSP y devuelve un array de contratos normalizados.
     */
    public function parseAtomFile(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $xml->registerXPathNamespace('at', 'http://www.w3.org/2005/Atom');
        $xml->registerXPathNamespace('cbc-place-ext', 'urn:dgpe:names:draft:codice:schema:xsd:PlacExtensionComponents-2');
        $xml->registerXPathNamespace('cac-place-ext', 'urn:dgpe:names:draft:codice:schema:xsd:PlacExtensionAggregateComponents-2');
        $xml->registerXPathNamespace('cbc', 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2');
        $xml->registerXPathNamespace('cac', 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2');

        $entries = $xml->xpath('//at:entry');
        $contracts = [];

        foreach ($entries as $entry) {
            try {
                $contracts[] = $this->parseEntry($entry);
            } catch (\Throwable $e) {
                logger()->warning('Error parsing PLACSP entry', [
                    'id' => (string) ($entry->id ?? 'unknown'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $contracts;
    }

    protected function parseEntry(SimpleXMLElement $entry): array
    {
        $entry->registerXPathNamespace('at', 'http://www.w3.org/2005/Atom');
        $entry->registerXPathNamespace('cbc-place-ext', 'urn:dgpe:names:draft:codice:schema:xsd:PlacExtensionComponents-2');
        $entry->registerXPathNamespace('cac-place-ext', 'urn:dgpe:names:draft:codice:schema:xsd:PlacExtensionAggregateComponents-2');
        $entry->registerXPathNamespace('cbc', 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2');
        $entry->registerXPathNamespace('cac', 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2');

        $folder = $this->xpath($entry, './/cac-place-ext:ContractFolderStatus');

        $data = [
            'external_id' => (string) $entry->id,
            'link' => $this->xpathAttr($entry, './/at:link[@rel="alternate"]', 'href')
                ?? $this->xpathAttr($entry, './/at:link', 'href'),
        ];

        if (!$folder) {
            $data['expediente'] = (string) $entry->title;
            $data['objeto'] = (string) $entry->title;
            $data['status_code'] = 'PUB';
            $data['organo_contratante'] = '';
            return $data;
        }

        // Expediente y estado
        $data['expediente'] = $this->xpathText($folder, 'cbc:ContractFolderID') ?? (string) $entry->title;
        $data['status_code'] = $this->xpathText($folder, 'cbc-place-ext:ContractFolderStatusCode') ?? 'PUB';

        // Órgano de contratación
        $party = $this->xpath($folder, './/cac-place-ext:LocatedContractingParty//cac:Party');
        if ($party) {
            $data['organo_contratante'] = $this->xpathText($party, './/cac:PartyName/cbc:Name') ?? '';
            $data['organo_dir3'] = $this->xpathText($party, './/cac:PartyIdentification/cbc:ID');
        } else {
            $data['organo_contratante'] = '';
        }

        $data['organo_superior'] = $this->xpathText($folder,
            './/cac-place-ext:LocatedContractingParty//cac-place-ext:ParentLocatedParty//cac:PartyName/cbc:Name');

        // Proyecto de contratación
        $project = $this->xpath($folder, './/cac:ProcurementProject');
        if ($project) {
            $data['objeto'] = $this->xpathText($project, 'cbc:Name') ?? (string) $entry->title;
            $data['tipo_contrato_code'] = $this->xpathText($project, 'cbc:TypeCode');
            $data['subtipo_contrato_code'] = $this->xpathText($project, 'cbc-place-ext:SubTypeCode');

            // Importes
            $budget = $this->xpath($project, './/cac:BudgetAmount');
            if ($budget) {
                $data['valor_estimado'] = $this->xpathDecimal($budget, 'cbc:EstimatedOverallContractAmount');
                $data['importe_con_iva'] = $this->xpathDecimal($budget, 'cbc:TotalAmount');
                $data['importe_sin_iva'] = $this->xpathDecimal($budget, 'cbc:TaxExclusiveAmount');
            }

            // CPV
            $cpvNodes = $project->xpath('.//cac:RequiredCommodityClassification/cbc:ItemClassificationCode');
            if ($cpvNodes) {
                $data['cpv_codes'] = array_map(fn($n) => (string) $n, $cpvNodes);
            }

            // Ubicación
            $location = $this->xpath($project, './/cac:RealizedLocation');
            if ($location) {
                $data['comunidad_autonoma'] = $this->xpathText($location, 'cbc:CountrySubentity');
                $data['nuts_code'] = $this->xpathText($location, 'cbc:CountrySubentityCode');
                $data['lugar_ejecucion'] = $this->xpathText($location, './/cac:Address/cbc:CityName');
            }

            // Duración
            $period = $this->xpath($project, './/cac:PlannedPeriod');
            if ($period) {
                $durNode = $period->xpath('cbc:DurationMeasure');
                if ($durNode) {
                    $data['duracion'] = (float) (string) $durNode[0];
                    $data['duracion_unidad'] = (string) ($durNode[0]['unitCode'] ?? 'MON');
                }
                $data['fecha_inicio'] = $this->xpathText($period, 'cbc:StartDate');
                $data['fecha_fin'] = $this->xpathText($period, 'cbc:EndDate');
            }
        } else {
            $data['objeto'] = (string) $entry->title;
        }

        // Proceso
        $process = $this->xpath($folder, './/cac:TenderingProcess');
        if ($process) {
            $data['procedimiento_code'] = $this->xpathText($process, 'cbc:ProcedureCode');
            $data['urgencia_code'] = $this->xpathText($process, 'cbc:UrgencyCode');

            $deadline = $this->xpathText($process, './/cac:TenderSubmissionDeadlinePeriod/cbc:EndDate');
            if ($deadline) {
                $data['fecha_presentacion_limite'] = $deadline;
            }
        }

        // Resultado (tomamos el primero)
        $result = $this->xpath($folder, './/cac:TenderResult');
        if ($result) {
            $data['resultado_code'] = $this->xpathText($result, 'cbc:ResultCode');
            $data['fecha_adjudicacion'] = $this->xpathText($result, 'cbc:AwardDate');
            $data['num_ofertas'] = $this->xpathInt($result, 'cbc:ReceivedTenderQuantity');

            // Adjudicatario
            $winner = $this->xpath($result, './/cac:WinningParty');
            if ($winner) {
                $data['adjudicatario_nombre'] = $this->xpathText($winner, './/cac:PartyName/cbc:Name');
                $data['adjudicatario_nif'] = $this->xpathText($winner, './/cac:PartyIdentification/cbc:ID');
            }

            // Importe adjudicación
            $awarded = $this->xpath($result, './/cac:AwardedTenderedProject//cac:LegalMonetaryTotal');
            if ($awarded) {
                $data['importe_adjudicacion_sin_iva'] = $this->xpathDecimal($awarded, 'cbc:TaxExclusiveAmount');
                $data['importe_adjudicacion_con_iva'] = $this->xpathDecimal($awarded, 'cbc:PayableAmount');
            }

            // Fecha formalización
            $contractNode = $this->xpath($result, 'cac:Contract');
            if ($contractNode) {
                $data['fecha_formalizacion'] = $this->xpathText($contractNode, 'cbc:IssueDate');
            }
        }

        return array_filter($data, fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    // --- Helpers ---

    protected function xpath(SimpleXMLElement $el, string $path): ?SimpleXMLElement
    {
        $result = $el->xpath($path);
        return $result ? $result[0] : null;
    }

    protected function xpathText(SimpleXMLElement $el, string $path): ?string
    {
        $result = $el->xpath($path);
        if (!$result) return null;
        $text = trim((string) $result[0]);
        return $text !== '' ? $text : null;
    }

    protected function xpathDecimal(SimpleXMLElement $el, string $path): ?float
    {
        $text = $this->xpathText($el, $path);
        return $text !== null ? (float) $text : null;
    }

    protected function xpathInt(SimpleXMLElement $el, string $path): ?int
    {
        $text = $this->xpathText($el, $path);
        return $text !== null ? (int) $text : null;
    }

    protected function xpathAttr(SimpleXMLElement $el, string $path, string $attr): ?string
    {
        $result = $el->xpath($path);
        if (!$result) return null;
        $val = (string) ($result[0][$attr] ?? '');
        return $val !== '' ? $val : null;
    }
}
