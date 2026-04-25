<?php

namespace Modules\Contracts\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Contracts\Models\Contract;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * FULLTEXT search across objeto + expediente with LIKE fallback.
 *
 * NATURAL LANGUAGE MODE applies the 50% threshold and ignores very short terms,
 * which makes it useless on small test datasets. For robust behaviour across
 * both production and unit-size fixtures we use IN BOOLEAN MODE (no threshold)
 * and fall back to LIKE when the tokenized term is too short (< 4 chars for
 * default InnoDB FT minimum word length).
 *
 * @implements Filter<Contract>
 */
class SearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return;
        }

        $term = trim((string) $value);
        if ($term === '') {
            return;
        }

        // Short terms or single words with special punctuation → LIKE fallback.
        if (mb_strlen($term) < 4) {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('objeto', 'LIKE', $like)
                    ->orWhere('expediente', 'LIKE', $like);
            });

            return;
        }

        // Boolean mode: append wildcard for partial matches; escape operators.
        $booleanTerm = '+'.str_replace(['+', '-', '(', ')', '<', '>', '~', '*', '"'], ' ', $term).'*';

        $query->whereRaw(
            'MATCH(objeto, expediente) AGAINST (? IN BOOLEAN MODE)',
            [$booleanTerm],
        );
    }
}
