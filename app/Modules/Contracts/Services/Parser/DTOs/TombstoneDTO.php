<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class TombstoneDTO
{
    public function __construct(
        public string $ref,
        public \DateTimeImmutable $when,
    ) {}
}
