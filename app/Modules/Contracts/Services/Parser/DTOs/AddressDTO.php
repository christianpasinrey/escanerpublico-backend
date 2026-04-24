<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class AddressDTO
{
    public function __construct(
        public ?string $line = null,
        public ?string $postal_code = null,
        public ?string $city_name = null,
        public ?string $country_code = null,
    ) {}
}
