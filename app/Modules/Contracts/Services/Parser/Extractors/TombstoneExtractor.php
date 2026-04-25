<?php

namespace Modules\Contracts\Services\Parser\Extractors;

use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;

class TombstoneExtractor
{
    public function extract(\SimpleXMLElement $deletedEntry): TombstoneDTO
    {
        $attrs = $deletedEntry->attributes();

        return new TombstoneDTO(
            ref: (string) $attrs['ref'],
            when: new \DateTimeImmutable((string) $attrs['when']),
        );
    }
}
