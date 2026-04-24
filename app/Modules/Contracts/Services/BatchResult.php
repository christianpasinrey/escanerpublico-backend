<?php

namespace Modules\Contracts\Services;

final readonly class BatchResult
{
    public function __construct(
        public int $processed,
        public int $skipped,
        public int $errored,
    ) {}
}
