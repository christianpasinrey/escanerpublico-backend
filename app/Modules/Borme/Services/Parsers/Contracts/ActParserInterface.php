<?php

namespace Modules\Borme\Services\Parsers\Contracts;

use Modules\Borme\DTOs\ActItemDTO;
use Modules\Borme\Enums\ActType;

interface ActParserInterface
{
    public function supports(): ActType;

    public function parse(string $entryBlock): ?ActItemDTO;
}
