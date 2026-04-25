<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\AddressDTO;
use Modules\Contracts\Services\Parser\DTOs\ContactDTO;
use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;

class OrganizationExtractor
{
    private const NS_CBC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2';

    private const NS_CAC_EXT = 'urn:dgpe:names:draft:codice-place-ext:schema:xsd:CommonAggregateComponents-2';

    public function extract(\SimpleXMLElement $locatedParty): OrganizationDTO
    {
        $typeCode = trim((string) $locatedParty->children(self::NS_CBC)->ContractingPartyTypeCode) ?: null;
        $activityCode = trim((string) $locatedParty->children(self::NS_CBC)->ActivityCode) ?: null;
        $buyerProfileUri = trim((string) $locatedParty->children(self::NS_CBC)->BuyerProfileURIID) ?: null;

        $name = '';
        $dir3 = $nif = $platformId = null;
        $address = null;
        $contacts = [];

        $party = $locatedParty->children(self::NS_CAC)->Party;
        if ($party && $party->count()) {
            $name = trim((string) $party->children(self::NS_CAC)->PartyName->children(self::NS_CBC)->Name);
            $websiteUri = trim((string) $party->children(self::NS_CBC)->WebsiteURI) ?: null;

            foreach ($party->children(self::NS_CAC)->PartyIdentification as $pid) {
                $idEl = $pid->children(self::NS_CBC)->ID;
                $scheme = (string) ($idEl->attributes()->schemeName ?? '');
                $value = trim((string) $idEl);
                if ($value === '') {
                    continue;
                }
                match ($scheme) {
                    'DIR3' => $dir3 = $value,
                    'NIF' => $nif = $value,
                    'ID_PLATAFORMA' => $platformId = $value,
                    default => null,
                };
            }

            // Address
            $postal = $party->children(self::NS_CAC)->PostalAddress;
            if ($postal && $postal->count()) {
                $addressLine = $postal->children(self::NS_CAC)->AddressLine;
                $line = '';
                if ($addressLine && $addressLine->count()) {
                    $line = trim((string) $addressLine->children(self::NS_CBC)->Line);
                }
                $cityName = trim((string) $postal->children(self::NS_CBC)->CityName);
                $postalCode = trim((string) $postal->children(self::NS_CBC)->PostalZone);
                $country = $postal->children(self::NS_CAC)->Country;
                $countryCode = '';
                if ($country && $country->count()) {
                    $countryCode = trim((string) $country->children(self::NS_CBC)->IdentificationCode);
                }

                if ($line !== '' || $cityName !== '' || $postalCode !== '' || $countryCode !== '') {
                    $address = new AddressDTO(
                        line: $line ?: null,
                        postal_code: $postalCode ?: null,
                        city_name: $cityName ?: null,
                        country_code: $countryCode ?: null,
                    );
                }
            }

            // Contacts
            $contact = $party->children(self::NS_CAC)->Contact;
            if ($contact && $contact->count()) {
                $cbc = $contact->children(self::NS_CBC);
                foreach ([
                    'phone' => 'Telephone',
                    'fax' => 'Telefax',
                    'email' => 'ElectronicMail',
                ] as $type => $tag) {
                    $v = trim((string) $cbc->{$tag});
                    if ($v !== '') {
                        $contacts[] = new ContactDTO($type, $v);
                    }
                }
            }

            if ($websiteUri) {
                $contacts[] = new ContactDTO('website', $websiteUri);
            }
        }

        // Hierarchy (walk ParentLocatedParty chain)
        $hierarchy = [];
        $parent = $locatedParty->children(self::NS_CAC_EXT)->ParentLocatedParty;
        while ($parent && $parent->count()) {
            $partyName = $parent->children(self::NS_CAC)->PartyName;
            if ($partyName && $partyName->count()) {
                $n = trim((string) $partyName->children(self::NS_CBC)->Name);
                if ($n !== '') {
                    $hierarchy[] = $n;
                }
            }
            $parent = $parent->children(self::NS_CAC_EXT)->ParentLocatedParty;
        }

        return new OrganizationDTO(
            name: $name,
            dir3: $dir3,
            nif: $nif,
            platform_id: $platformId,
            buyer_profile_uri: $buyerProfileUri,
            activity_code: $activityCode,
            type_code: $typeCode,
            hierarchy: $hierarchy,
            address: $address,
            contacts: $contacts,
        );
    }
}
