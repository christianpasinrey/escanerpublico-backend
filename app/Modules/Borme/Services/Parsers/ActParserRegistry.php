<?php

namespace Modules\Borme\Services\Parsers;

use Modules\Borme\Enums\ActType;
use Modules\Borme\Services\Parsers\Contracts\ActParserInterface;

class ActParserRegistry
{
    /** @var array<string, ActParserInterface> */
    private array $byType = [];

    /**
     * @param  iterable<ActParserInterface>  $parsers
     */
    public function __construct(iterable $parsers = [])
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
    }

    public function register(ActParserInterface $parser): void
    {
        $this->byType[$parser->supports()->value] = $parser;
    }

    public function for(ActType $type): ?ActParserInterface
    {
        return $this->byType[$type->value] ?? null;
    }
}
