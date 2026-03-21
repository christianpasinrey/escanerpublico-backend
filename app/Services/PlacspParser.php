<?php

namespace App\Services;

use SimpleXMLElement;

class PlacspParser
{
    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';
    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';
    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonBasicComponents-2';

    public function parseAtomFile(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $contracts = [];

        // Acceder a entries por namespace Atom
        foreach ($xml->children(self::NS_ATOM)->entry as $entry) {
            try {
                $parsed = $this->parseEntry($entry);
                if ($parsed) {
                    $contracts[] = $parsed;
                }
            } catch (\Throwable $e) {
                logger()->warning('Error parsing PLACSP entry', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $contracts;
    }

    protected function parseEntry(SimpleXMLElement $entry): ?array
    {
        // Atom fields
        $atomChildren = $entry->children(self::NS_ATOM);
        $id = trim((string) $atomChildren->id);
        $title = trim((string) $atomChildren->title);
        // <link> es hijo directo del entry en el namespace por defecto (Atom)
        // Acceder via children sin namespace ya que Atom es el default ns del feed
        $link = '';
        foreach ($entry->link as $l) {
            $href = (string) ($l->attributes()['href'] ?? '');
            if ($href) {
                $link = $href;
                break;
            }
        }
        // Fallback: intentar con namespace explícito
        if (!$link) {
            foreach ($entry->children(self::NS_ATOM)->link as $l) {
                $href = (string) ($l->attributes()['href'] ?? '');
                if ($href) {
                    $link = $href;
                    break;
                }
            }
        }

        if (!$id) return null;

        // ContractFolderStatus (namespace cac-place-ext)
        $folder = $entry->children(self::NS_CAC_EXT)->ContractFolderStatus;
        if (!$folder || !$folder->count()) {
            // Entry sin datos de contrato — skip
            return null;
        }

        $data = [
            'external_id' => $id,
            'link' => $link ?: null,
        ];

        // Expediente
        $cbcChildren = $folder->children(self::NS_CBC);
        $data['expediente'] = trim((string) $cbcChildren->ContractFolderID) ?: mb_substr($title, 0, 490);

        // Estado
        $cbcExtChildren = $folder->children(self::NS_CBC_EXT);
        $data['status_code'] = trim((string) $cbcExtChildren->ContractFolderStatusCode) ?: 'PUB';

        // Órgano de contratación
        $cacExtChildren = $folder->children(self::NS_CAC_EXT);
        $locatedParty = $cacExtChildren->LocatedContractingParty;
        if ($locatedParty && $locatedParty->count()) {
            // ContractingPartyTypeCode
            $orgTypeCode = trim((string) $locatedParty->children(self::NS_CBC)->ContractingPartyTypeCode);
            if ($orgTypeCode) $data['organo_tipo_code'] = $orgTypeCode;

            $party = $locatedParty->children(self::NS_CAC)->Party;
            if ($party && $party->count()) {
                $partyCbc = $party->children(self::NS_CBC);

                // Website
                $website = trim((string) $partyCbc->WebsiteURI);
                if ($website) $data['organo_website'] = $website;

                // Party name
                $partyName = $party->children(self::NS_CAC)->PartyName;
                $data['organo_contratante'] = trim((string) $partyName->children(self::NS_CBC)->Name);

                // Party identifications (DIR3, NIF)
                foreach ($party->children(self::NS_CAC)->PartyIdentification as $pid) {
                    $idEl = $pid->children(self::NS_CBC)->ID;
                    $scheme = (string) $idEl['schemeName'];
                    if ($scheme === 'DIR3') {
                        $data['organo_dir3'] = trim((string) $idEl);
                    } elseif ($scheme === 'NIF') {
                        $data['organo_nif'] = trim((string) $idEl);
                    }
                }

                // Postal address
                $address = $party->children(self::NS_CAC)->PostalAddress;
                if ($address && $address->count()) {
                    $addrCbc = $address->children(self::NS_CBC);
                    $data['organo_ciudad'] = trim((string) $addrCbc->CityName) ?: null;
                    $data['organo_cp'] = trim((string) $addrCbc->PostalZone) ?: null;
                    $addressLine = $address->children(self::NS_CAC)->AddressLine;
                    if ($addressLine && $addressLine->count()) {
                        $data['organo_direccion'] = trim((string) $addressLine->children(self::NS_CBC)->Line) ?: null;
                    }
                }

                // Contact
                $contact = $party->children(self::NS_CAC)->Contact;
                if ($contact && $contact->count()) {
                    $contactCbc = $contact->children(self::NS_CBC);
                    $data['organo_telefono'] = trim((string) $contactCbc->Telephone) ?: null;
                    $data['organo_email'] = trim((string) $contactCbc->ElectronicMail) ?: null;
                }
            }

            // Org hierarchy — walk the recursive ParentLocatedParty chain
            $hierarchy = [];
            $parentNode = $locatedParty->children(self::NS_CAC_EXT)->ParentLocatedParty;
            while ($parentNode && $parentNode->count()) {
                $ppName = $parentNode->children(self::NS_CAC)->PartyName;
                $name = trim((string) $ppName->children(self::NS_CBC)->Name);
                if ($name) $hierarchy[] = $name;
                $parentNode = $parentNode->children(self::NS_CAC_EXT)->ParentLocatedParty;
            }
            if ($hierarchy) {
                $data['organo_jerarquia'] = $hierarchy;
                $data['organo_superior'] = $hierarchy[0];
            }
        }

        if (empty($data['organo_contratante'])) {
            $data['organo_contratante'] = '';
        }

        // ProcurementProject
        $project = $folder->children(self::NS_CAC)->ProcurementProject;
        if ($project && $project->count()) {
            $projCbc = $project->children(self::NS_CBC);
            $data['objeto'] = trim((string) $projCbc->Name) ?: $title;
            $data['tipo_contrato_code'] = trim((string) $projCbc->TypeCode) ?: null;

            $projCbcExt = $project->children(self::NS_CBC_EXT);
            $data['subtipo_contrato_code'] = trim((string) $projCbcExt->SubTypeCode) ?: null;

            // Budget
            $budget = $project->children(self::NS_CAC)->BudgetAmount;
            if ($budget && $budget->count()) {
                $budgetCbc = $budget->children(self::NS_CBC);
                $data['valor_estimado'] = $this->decimal($budgetCbc->EstimatedOverallContractAmount);
                $data['importe_con_iva'] = $this->decimal($budgetCbc->TotalAmount);
                $data['importe_sin_iva'] = $this->decimal($budgetCbc->TaxExclusiveAmount);
            }

            // CPV
            $cpvCodes = [];
            foreach ($project->children(self::NS_CAC)->RequiredCommodityClassification as $cpvClass) {
                $code = trim((string) $cpvClass->children(self::NS_CBC)->ItemClassificationCode);
                if ($code) $cpvCodes[] = $code;
            }
            if ($cpvCodes) $data['cpv_codes'] = $cpvCodes;

            // Location
            $location = $project->children(self::NS_CAC)->RealizedLocation;
            if ($location && $location->count()) {
                $locCbc = $location->children(self::NS_CBC);
                $data['comunidad_autonoma'] = trim((string) $locCbc->CountrySubentity) ?: null;
                $data['nuts_code'] = trim((string) $locCbc->CountrySubentityCode) ?: null;

                $addr = $location->children(self::NS_CAC)->Address;
                if ($addr && $addr->count()) {
                    $data['lugar_ejecucion'] = trim((string) $addr->children(self::NS_CBC)->CityName) ?: null;
                }
            }

            // Duration
            $period = $project->children(self::NS_CAC)->PlannedPeriod;
            if ($period && $period->count()) {
                $periodCbc = $period->children(self::NS_CBC);
                $dur = $periodCbc->DurationMeasure;
                if (trim((string) $dur)) {
                    $data['duracion'] = (float) trim((string) $dur);
                    $data['duracion_unidad'] = (string) ($dur['unitCode'] ?? 'MON');
                }
                $data['fecha_inicio'] = $this->dateVal($periodCbc->StartDate);
                $data['fecha_fin'] = $this->dateVal($periodCbc->EndDate);
            }
        } else {
            $data['objeto'] = $title;
        }

        // TenderingProcess
        $process = $folder->children(self::NS_CAC)->TenderingProcess;
        if ($process && $process->count()) {
            $procCbc = $process->children(self::NS_CBC);
            $data['procedimiento_code'] = trim((string) $procCbc->ProcedureCode) ?: null;
            $data['urgencia_code'] = trim((string) $procCbc->UrgencyCode) ?: null;
            $data['submission_method_code'] = trim((string) $procCbc->SubmissionMethodCode) ?: null;
            $data['contracting_system_code'] = trim((string) $procCbc->ContractingSystemCode) ?: null;

            // Document availability period
            $docAvail = $process->children(self::NS_CAC)->DocumentAvailabilityPeriod;
            if ($docAvail && $docAvail->count()) {
                $data['fecha_disponibilidad_docs'] = $this->dateVal($docAvail->children(self::NS_CBC)->EndDate);
            }

            // Submission deadline
            $deadline = $process->children(self::NS_CAC)->TenderSubmissionDeadlinePeriod;
            if ($deadline && $deadline->count()) {
                $dlCbc = $deadline->children(self::NS_CBC);
                $data['fecha_presentacion_limite'] = $this->dateVal($dlCbc->EndDate);
                $time = trim((string) $dlCbc->EndTime);
                if ($time) $data['hora_presentacion_limite'] = $time;
            }
        }

        // Warn if multiple TenderResult (multi-lot — only first is captured for now)
        $tenderResults = $folder->children(self::NS_CAC)->TenderResult;
        if ($tenderResults && $tenderResults->count() > 1) {
            logger()->info("PLACSP: Contrato {$data['expediente']} tiene {$tenderResults->count()} TenderResult (multi-lote). Solo se captura el primero.");
        }

        // TenderResult (primer resultado)
        $result = $folder->children(self::NS_CAC)->TenderResult;
        if ($result && $result->count()) {
            $resCbc = $result->children(self::NS_CBC);
            $data['resultado_code'] = trim((string) $resCbc->ResultCode) ?: null;
            $data['fecha_adjudicacion'] = $this->dateVal($resCbc->AwardDate);
            $qty = trim((string) $resCbc->ReceivedTenderQuantity);
            if ($qty !== '') $data['num_ofertas'] = (int) $qty;

            // Winner
            $winner = $result->children(self::NS_CAC)->WinningParty;
            if ($winner && $winner->count()) {
                $wName = $winner->children(self::NS_CAC)->PartyName;
                $data['adjudicatario_nombre'] = trim((string) $wName->children(self::NS_CBC)->Name) ?: null;

                $wId = $winner->children(self::NS_CAC)->PartyIdentification;
                if ($wId && $wId->count()) {
                    $data['adjudicatario_nif'] = trim((string) $wId->children(self::NS_CBC)->ID) ?: null;
                }
            }

            // Awarded amount
            $awarded = $result->children(self::NS_CAC)->AwardedTenderedProject;
            if ($awarded && $awarded->count()) {
                $lmt = $awarded->children(self::NS_CAC)->LegalMonetaryTotal;
                if ($lmt && $lmt->count()) {
                    $lmtCbc = $lmt->children(self::NS_CBC);
                    $data['importe_adjudicacion_sin_iva'] = $this->decimal($lmtCbc->TaxExclusiveAmount);
                    $data['importe_adjudicacion_con_iva'] = $this->decimal($lmtCbc->PayableAmount);
                }
            }

            // SME indicator
            $sme = trim((string) $resCbc->SMEAwardedIndicator);
            if ($sme !== '') $data['sme_awarded'] = $sme === 'true';

            // Contract number (modify existing Contract block)
            $contract = $result->children(self::NS_CAC)->Contract;
            if ($contract && $contract->count()) {
                $data['fecha_formalizacion'] = $this->dateVal($contract->children(self::NS_CBC)->IssueDate);
                $contractId = trim((string) $contract->children(self::NS_CBC)->ID);
                if ($contractId) $data['contrato_numero'] = $contractId;
            }
        }

        // TenderingTerms
        $terms = $folder->children(self::NS_CAC)->TenderingTerms;
        if ($terms && $terms->count()) {
            // Language
            $lang = $terms->children(self::NS_CAC)->Language;
            if ($lang && $lang->count()) {
                $data['idioma'] = trim((string) $lang->children(self::NS_CBC)->ID) ?: null;
            }

            // Financial guarantee
            $guarantee = $terms->children(self::NS_CAC)->RequiredFinancialGuarantee;
            if ($guarantee && $guarantee->count()) {
                $gCbc = $guarantee->children(self::NS_CBC);
                $data['garantia_tipo_code'] = trim((string) $gCbc->GuaranteeTypeCode) ?: null;
                $rate = trim((string) $gCbc->AmountRate);
                if ($rate !== '') $data['garantia_porcentaje'] = (float) $rate;
            }

            // Awarding criteria
            $awardingTerms = $terms->children(self::NS_CAC)->AwardingTerms;
            if ($awardingTerms && $awardingTerms->count()) {
                $criterios = [];
                foreach ($awardingTerms->children(self::NS_CAC)->AwardingCriteria as $criteria) {
                    $critCbc = $criteria->children(self::NS_CBC);
                    $desc = trim((string) $critCbc->Description);
                    $weight = trim((string) $critCbc->WeightNumeric);
                    if ($desc) {
                        $criterios[] = [
                            'description' => $desc,
                            'weight' => $weight !== '' ? (float) $weight : null,
                        ];
                    }
                }
                if ($criterios) $data['criterios_adjudicacion'] = $criterios;
            }

            // Contract extension options
            $extension = $folder->children(self::NS_CAC)->ProcurementProject
                ?->children(self::NS_CAC)->ContractExtension;
            if ($extension && $extension->count()) {
                $opts = trim((string) $extension->children(self::NS_CBC)->OptionsDescription);
                if ($opts) $data['opciones_descripcion'] = $opts;
            }
        }

        // ValidNoticeInfo — timeline notices
        $notices = [];
        foreach ($folder->children(self::NS_CAC_EXT)->ValidNoticeInfo as $vni) {
            $noticeType = trim((string) $vni->children(self::NS_CBC_EXT)->NoticeTypeCode);
            if (!$noticeType) continue;

            $pubStatus = $vni->children(self::NS_CAC_EXT)->AdditionalPublicationStatus;
            if (!$pubStatus || !$pubStatus->count()) continue;

            $mediaName = trim((string) $pubStatus->children(self::NS_CBC_EXT)->PublicationMediaName);

            foreach ($pubStatus->children(self::NS_CAC_EXT)->AdditionalPublicationDocumentReference as $docRef) {
                $notice = [
                    'notice_type_code' => $noticeType,
                    'publication_media' => $mediaName ?: null,
                    'issue_date' => $this->dateVal($docRef->children(self::NS_CBC)->IssueDate),
                ];

                // Optional document attachment
                $docTypeCode = $docRef->children(self::NS_CBC)->DocumentTypeCode;
                if ($docTypeCode && trim((string) $docTypeCode)) {
                    $notice['document_type_code'] = trim((string) $docTypeCode);
                    $notice['document_type_name'] = (string) ($docTypeCode['name'] ?? null) ?: null;
                }

                $attachment = $docRef->children(self::NS_CAC)->Attachment;
                if ($attachment && $attachment->count()) {
                    $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
                    if ($extRef && $extRef->count()) {
                        $extCbc = $extRef->children(self::NS_CBC);
                        $notice['document_uri'] = trim((string) $extCbc->URI) ?: null;
                        $notice['document_filename'] = trim((string) $extCbc->FileName) ?: null;
                    }
                }

                $notices[] = $notice;
            }
        }
        if ($notices) $data['_notices'] = $notices;

        // Document references (Legal + Technical + Additional)
        $docRefs = [];

        foreach ($folder->children(self::NS_CAC)->LegalDocumentReference as $docRef) {
            $docRefs[] = $this->parseDocumentReference($docRef, 'legal');
        }
        foreach ($folder->children(self::NS_CAC)->TechnicalDocumentReference as $docRef) {
            $docRefs[] = $this->parseDocumentReference($docRef, 'technical');
        }
        foreach ($folder->children(self::NS_CAC)->AdditionalDocumentReference as $docRef) {
            $docRefs[] = $this->parseDocumentReference($docRef, 'additional');
        }
        // GeneralDocument (cac-place-ext)
        foreach ($folder->children(self::NS_CAC_EXT)->GeneralDocument as $genDoc) {
            $ref = $genDoc->children(self::NS_CAC_EXT)->GeneralDocumentDocumentReference
                ?? $genDoc->children(self::NS_CAC)->DocumentReference
                ?? null;
            if ($ref && $ref->count()) {
                $docRefs[] = $this->parseDocumentReference($ref, 'general');
            }
        }

        if ($docRefs) $data['_documents'] = array_filter($docRefs);

        return array_filter($data, fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function decimal(SimpleXMLElement $el): ?float
    {
        $val = trim((string) $el);
        return $val !== '' ? (float) $val : null;
    }

    private function dateVal(SimpleXMLElement $el): ?string
    {
        $val = trim((string) $el);
        return $val !== '' ? $val : null;
    }

    private function parseDocumentReference(SimpleXMLElement $docRef, string $type): ?array
    {
        $name = trim((string) $docRef->children(self::NS_CBC)->ID);
        if (!$name) return null;

        $doc = [
            'type' => $type,
            'name' => $name,
        ];

        $attachment = $docRef->children(self::NS_CAC)->Attachment;
        if ($attachment && $attachment->count()) {
            $extRef = $attachment->children(self::NS_CAC)->ExternalReference;
            if ($extRef && $extRef->count()) {
                $extCbc = $extRef->children(self::NS_CBC);
                $doc['uri'] = trim((string) $extCbc->URI) ?: null;
                $doc['hash'] = trim((string) $extCbc->DocumentHash) ?: null;

                // For GeneralDocument, the ID is often a UUID — prefer FileName if available
                $fileName = trim((string) $extCbc->FileName);
                if ($fileName) {
                    $doc['name'] = $fileName;
                }
            }
        }

        return $doc;
    }
}
