<?php

namespace Modules\Contracts\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Contracts\Models\Contract;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * @implements Filter<Contract>
 */
class AmountBetweenFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $min = is_numeric($parts[0] ?? null) ? (float) $parts[0] : null;
        $max = is_numeric($parts[1] ?? null) ? (float) $parts[1] : null;

        if ($min !== null) {
            $query->where('importe_con_iva', '>=', $min);
        }
        if ($max !== null) {
            $query->where('importe_con_iva', '<=', $max);
        }
    }
}
