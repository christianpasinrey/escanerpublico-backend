<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class WinningPartyDTO
{
    public function __construct(
        public string $name,
        public ?string $nif,
        public ?AddressDTO $address,
    ) {}
}
