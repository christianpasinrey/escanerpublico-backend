<?php

namespace Modules\Contracts\Http\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RelevanceSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $search = request()->query('filter.search');
        if (! is_string($search) || $search === '') {
            return $query->orderBy('snapshot_updated_at', $descending ? 'desc' : 'asc');
        }

        return $query->orderByRaw(
            'MATCH(objeto, expediente) AGAINST (? IN NATURAL LANGUAGE MODE) '.($descending ? 'DESC' : 'ASC'),
            [$search],
        );
    }
}
