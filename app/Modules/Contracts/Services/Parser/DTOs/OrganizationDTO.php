<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class OrganizationDTO
{
    /**
     * @param  string[]  $hierarchy
     * @param  ContactDTO[]  $contacts
     */
    public function __construct(
        public string $name,
        public ?string $dir3,
        public ?string $nif,
        public ?string $platform_id,
        public ?string $buyer_profile_uri,
        public ?string $activity_code,
        public ?string $type_code,
        public array $hierarchy,
        public ?AddressDTO $address,
        public array $contacts,
    ) {}
}
