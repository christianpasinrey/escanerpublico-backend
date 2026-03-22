<?php

namespace Modules\Contracts\Services;

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

        // ── Órgano de contratación → _organization ──
        $cacExtChildren = $folder->children(self::NS_CAC_EXT);
        $locatedParty = $cacExtChildren->LocatedContractingParty;

        $orgName = '';
        $orgTypeCode = null;
        $orgDir3 = null;
        $orgNif = null;
        $hierarchy = [];
        $addressLine = null;
        $postalZone = null;
        $cityName = null;
        $countryCode = null;
        $phone = null;
        $fax = null;
        $email = null;
        $website = null;

        if ($locatedParty && $locatedParty->count()) {
            $orgTypeCode = trim((string) $locatedParty->children(self::NS_CBC)->ContractingPartyTypeCode) ?: null;

            $party = $locatedParty->children(self::NS_CAC)->Party;
            if ($party && $party->count()) {
                $partyCbc = $party->children(self::NS_CBC);

                // Website
                $website = trim((string) $partyCbc->WebsiteURI) ?: null;

                // Party name
                $partyName = $party->children(self::NS_CAC)->PartyName;
                $orgName = trim((string) $partyName->children(self::NS_CBC)->Name);

                // Party identifications (DIR3, NIF)
                foreach ($party->children(self::NS_CAC)->PartyIdentification as $pid) {
                    $idEl = $pid->children(self::NS_CBC)->ID;
                    $scheme = (string) $idEl['schemeName'];
                    if ($scheme === 'DIR3') {
                        $orgDir3 = trim((string) $idEl) ?: null;
                    } elseif ($scheme === 'NIF') {
                        $orgNif = trim((string) $idEl) ?: null;
                    }
                }

                // Postal address
                $address = $party->children(self::NS_CAC)->PostalAddress;
                if ($address && $address->count()) {
                    $addrCbc = $address->children(self::NS_CBC);
                    $cityName = trim((string) $addrCbc->CityName) ?: null;
                    $postalZone = trim((string) $addrCbc->PostalZone) ?: null;

                    $addrLine = $address->children(self::NS_CAC)->AddressLine;
                    if ($addrLine && $addrLine->count()) {
                        $addressLine = trim((string) $addrLine->children(self::NS_CBC)->Line) ?: null;
                    }

                    // Country
                    $country = $address->children(self::NS_CAC)->Country;
                    if ($country && $country->count()) {
                        $countryCode = trim((string) $country->children(self::NS_CBC)->IdentificationCode) ?: null;
                    }
                }

                // Contact
                $contact = $party->children(self::NS_CAC)->Contact;
                if ($contact && $contact->count()) {
                    $contactCbc = $contact->children(self::NS_CBC);
                    $phone = trim((string) $contactCbc->Telephone) ?: null;
                    $fax = trim((string) $contactCbc->Telefax) ?: null;
                    $email = trim((string) $contactCbc->ElectronicMail) ?: null;
                }
            }

            // Org hierarchy — walk the recursive ParentLocatedParty chain
            $parentNode = $locatedParty->children(self::NS_CAC_EXT)->ParentLocatedParty;
            while ($parentNode && $parentNode->count()) {
                $ppName = $parentNode->children(self::NS_CAC)->PartyName;
                $name = trim((string) $ppName->children(self::NS_CBC)->Name);
                if ($name) $hierarchy[] = $name;
                $parentNode = $parentNode->children(self::NS_CAC_EXT)->ParentLocatedParty;
            }
        }

        // Build _organization sub-array
        $orgData = [
            'name' => $orgName ?: '',
            'identifier' => $orgDir3,
            'nif' => $orgNif,
            'type_code' => $orgTypeCode,
            'hierarchy' => $hierarchy ?: null,
            'parent_name' => $hierarchy[0] ?? null,
        ];

        // Address sub-array
        $addressArr = array_filter([
            'line' => $addressLine,
            'postal_code' => $postalZone,
            'city_name' => $cityName,
            'country_code' => $countryCode,
        ], fn($v) => $v !== null && $v !== '');

        if ($addressArr) $orgData['_address'] = $addressArr;

        // Contacts sub-array
        $contacts = [];
        if ($phone) $contacts[] = ['type' => 'phone', 'value' => $phone];
        if ($fax) $contacts[] = ['type' => 'fax', 'value' => $fax];
        if ($email) $contacts[] = ['type' => 'email', 'value' => $email];
        if ($website) $contacts[] = ['type' => 'website', 'value' => $website];
        if ($contacts) $orgData['_contacts'] = $contacts;

        $data['_organization'] = array_filter($orgData, fn($v) => $v !== null && $v !== '' && $v !== []);

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
        $procedureCode = null;
        $urgencyCode = null;
        if ($process && $process->count()) {
            $procCbc = $process->children(self::NS_CBC);
            $procedureCode = trim((string) $procCbc->ProcedureCode) ?: null;
            $urgencyCode = trim((string) $procCbc->UrgencyCode) ?: null;
            $data['procedimiento_code'] = $procedureCode;
            $data['urgencia_code'] = $urgencyCode;
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

        // ── TenderResult → _award ──
        $result = $folder->children(self::NS_CAC)->TenderResult;
        if ($result && $result->count()) {
            $resCbc = $result->children(self::NS_CBC);

            $resultCode = trim((string) $resCbc->ResultCode) ?: null;
            $awardDate = $this->dateVal($resCbc->AwardDate);
            $numOffers = null;
            $qty = trim((string) $resCbc->ReceivedTenderQuantity);
            if ($qty !== '') $numOffers = (int) $qty;

            // Winner
            $companyName = null;
            $companyNif = null;
            $winner = $result->children(self::NS_CAC)->WinningParty;
            if ($winner && $winner->count()) {
                $wName = $winner->children(self::NS_CAC)->PartyName;
                $companyName = trim((string) $wName->children(self::NS_CBC)->Name) ?: null;

                $wId = $winner->children(self::NS_CAC)->PartyIdentification;
                if ($wId && $wId->count()) {
                    $companyNif = trim((string) $wId->children(self::NS_CBC)->ID) ?: null;
                }
            }

            // Awarded amount
            $awardAmountWithTax = null;
            $awardAmountWithoutTax = null;
            $awarded = $result->children(self::NS_CAC)->AwardedTenderedProject;
            if ($awarded && $awarded->count()) {
                $lmt = $awarded->children(self::NS_CAC)->LegalMonetaryTotal;
                if ($lmt && $lmt->count()) {
                    $lmtCbc = $lmt->children(self::NS_CBC);
                    $awardAmountWithoutTax = $this->decimal($lmtCbc->TaxExclusiveAmount);
                    $awardAmountWithTax = $this->decimal($lmtCbc->PayableAmount);
                }
            }

            // SME indicator
            $smeAwarded = null;
            $sme = trim((string) $resCbc->SMEAwardedIndicator);
            if ($sme !== '') $smeAwarded = $sme === 'true';

            // Contract number + formalization date
            $contractNumber = null;
            $formalizationDate = null;
            $contract = $result->children(self::NS_CAC)->Contract;
            if ($contract && $contract->count()) {
                $formalizationDate = $this->dateVal($contract->children(self::NS_CBC)->IssueDate);
                $cid = trim((string) $contract->children(self::NS_CBC)->ID);
                if ($cid) $contractNumber = $cid;
            }

            // Build _award sub-array
            $awardData = [
                'company_name' => $companyName,
                'company_nif' => $companyNif,
                'amount' => $awardAmountWithTax,
                'amount_without_tax' => $awardAmountWithoutTax,
                'award_date' => $awardDate,
                'formalization_date' => $formalizationDate,
                'contract_number' => $contractNumber,
                'sme_awarded' => $smeAwarded,
                'num_offers' => $numOffers,
                'result_code' => $resultCode,
                'procedure_type' => $procedureCode,
                'urgency' => $urgencyCode,
            ];

            $data['_award'] = array_filter($awardData, fn($v) => $v !== null && $v !== '');
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
