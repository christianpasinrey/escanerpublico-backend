<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\AddressDTO;
use Modules\Contracts\Services\Parser\DTOs\ResultDTO;
use Modules\Contracts\Services\Parser\DTOs\WinningPartyDTO;

class ResultsExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    /** @return ResultDTO[] */
    public function extract(\SimpleXMLElement $folder): array
    {
        $results = [];
        $i = 1;

        foreach ($folder->children(self::NS_CAC)->TenderResult as $result) {
            $cbc = $result->children(self::NS_CBC);
            $cac = $result->children(self::NS_CAC);

            // Determine lot number (some multi-lote results carry a lot reference; default to positional)
            $lotNumber = $i;
            $lotId = trim((string) $cbc->ProcurementProjectLotID);
            if (ctype_digit($lotId)) {
                $lotNumber = (int) $lotId;
            }

            // Winner
            $winner = null;
            $winningParty = $cac->WinningParty;
            if ($winningParty && $winningParty->count()) {
                $partyName = $winningParty->children(self::NS_CAC)->PartyName;
                $wName = '';
                if ($partyName && $partyName->count()) {
                    $wName = trim((string) $partyName->children(self::NS_CBC)->Name);
                }

                $wNif = null;
                foreach ($winningParty->children(self::NS_CAC)->PartyIdentification as $pid) {
                    $wNif = trim((string) $pid->children(self::NS_CBC)->ID) ?: null;
                    if ($wNif) {
                        break;
                    }
                }

                // Physical location (address of winner)
                $wAddress = null;
                $physLoc = $winningParty->children(self::NS_CAC)->PhysicalLocation;
                if ($physLoc && $physLoc->count()) {
                    $addr = $physLoc->children(self::NS_CAC)->Address;
                    if ($addr && $addr->count()) {
                        $lineEl = $addr->children(self::NS_CAC)->AddressLine;
                        $line = '';
                        if ($lineEl && $lineEl->count()) {
                            $line = trim((string) $lineEl->children(self::NS_CBC)->Line);
                        }
                        $city = trim((string) $addr->children(self::NS_CBC)->CityName);
                        $postal = trim((string) $addr->children(self::NS_CBC)->PostalZone);
                        $countryEl = $addr->children(self::NS_CAC)->Country;
                        $country = '';
                        if ($countryEl && $countryEl->count()) {
                            $country = trim((string) $countryEl->children(self::NS_CBC)->IdentificationCode);
                        }
                        if ($line !== '' || $city !== '' || $postal !== '' || $country !== '') {
                            $wAddress = new AddressDTO(
                                line: $line ?: null,
                                postal_code: $postal ?: null,
                                city_name: $city ?: null,
                                country_code: $country ?: null,
                            );
                        }
                    }
                }

                if ($wName !== '') {
                    $winner = new WinningPartyDTO($wName, $wNif, $wAddress);
                }
            }

            // Amounts
            $amountWith = null;
            $amountWithout = null;
            $awarded = $cac->AwardedTenderedProject;
            if ($awarded && $awarded->count()) {
                $lmt = $awarded->children(self::NS_CAC)->LegalMonetaryTotal;
                if ($lmt && $lmt->count()) {
                    $amountWith = $this->decimal($lmt->children(self::NS_CBC)->PayableAmount);
                    $amountWithout = $this->decimal($lmt->children(self::NS_CBC)->TaxExclusiveAmount);
                }
            }

            // Contract number + formalization
            $contractNumber = null;
            $formalizationDate = null;
            $contract = $cac->Contract;
            if ($contract && $contract->count()) {
                $contractNumber = trim((string) $contract->children(self::NS_CBC)->ID) ?: null;
                $formalizationDate = trim((string) $contract->children(self::NS_CBC)->IssueDate) ?: null;
            }

            $sme = trim((string) $cbc->SMEAwardedIndicator);
            $smeAwarded = $sme === '' ? null : ($sme === 'true');

            $numOffersStr = trim((string) $cbc->ReceivedTenderQuantity);
            $smesQtyStr = trim((string) $cbc->SMEsReceivedTenderQuantity);

            $results[] = new ResultDTO(
                lot_number: $lotNumber,
                winner: $winner,
                amount_with_tax: $amountWith,
                amount_without_tax: $amountWithout,
                lower_tender_amount: $this->decimal($cbc->LowerTenderAmount),
                higher_tender_amount: $this->decimal($cbc->HigherTenderAmount),
                num_offers: $numOffersStr === '' ? null : (int) $numOffersStr,
                smes_received_tender_quantity: $smesQtyStr === '' ? null : (int) $smesQtyStr,
                sme_awarded: $smeAwarded,
                award_date: trim((string) $cbc->AwardDate) ?: null,
                start_date: trim((string) $cbc->StartDate) ?: null,
                formalization_date: $formalizationDate,
                contract_number: $contractNumber,
                result_code: trim((string) $cbc->ResultCode) ?: null,
                description: trim((string) $cbc->Description) ?: null,
            );

            $i++;
        }

        return $results;
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
