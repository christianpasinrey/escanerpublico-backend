<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ContactDTO
{
    public function __construct(
        public string $type,  // phone | fax | email | website
        public string $value,
    ) {}
}
