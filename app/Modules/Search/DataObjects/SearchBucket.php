<?php

namespace Modules\Search\DataObjects;

/**
 * Conjunto de hits del mismo dominio. Lo devuelve cada SearchProvider.
 */
final class SearchBucket
{
    /**
     * @param  list<SearchHit>  $hits  hits ya truncados al límite del provider
     * @param  int  $total  total real (puede ser > count($hits) si
     *                      hay más matches que el límite)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $hits,
        public readonly int $total,
    ) {}

    public static function empty(string $key, string $label): self
    {
        return new self($key, $label, [], 0);
    }
}
