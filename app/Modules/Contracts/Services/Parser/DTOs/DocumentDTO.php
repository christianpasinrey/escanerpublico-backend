<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class DocumentDTO
{
    public function __construct(
        public string $type,
        public string $name,
        public ?string $uri,
        public ?string $hash,
    ) {}
}
