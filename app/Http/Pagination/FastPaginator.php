<?php

namespace App\Http\Pagination;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Paginador con count aproximado para tablas masivas.
 *
 * El `paginate()` estándar de Laravel ejecuta `SELECT COUNT(*)` antes de la query
 * de datos. Sobre tablas con millones de filas (contracts, awards, subsidy_grants,
 * boe_items) el COUNT recorre todos los registros que cumplen el WHERE — coste
 * prohibitivo bajo carga concurrente.
 *
 * Estrategia:
 *  1. Si NO hay filtros aplicados (state-filtering), usamos
 *     `information_schema.TABLES.TABLE_ROWS` para obtener un count aproximado
 *     instantáneo.
 *  2. Si HAY filtros, cacheamos el COUNT(*) por una clave derivada del SQL+bindings
 *     durante 5 minutos (Redis). El primer hit pega MySQL, los siguientes son free.
 *  3. Devuelve un LengthAwarePaginator estándar — el frontend no nota cambios.
 *
 * Para tablas pequeñas (< 50k rows) se delega al paginate() estándar — no merece la
 * pena la complejidad.
 */
class FastPaginator
{
    private const SMALL_TABLE_THRESHOLD = 50_000;

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function paginate($query, int $perPage, int $page, string $tableName, bool $hasFilters): LengthAwarePaginator
    {
        $tableRows = self::approximateRowCount($tableName);

        if ($tableRows < self::SMALL_TABLE_THRESHOLD) {
            // Tabla pequeña: delegamos al paginate normal (count exacto barato)
            return self::standardPaginate($query, $perPage, $page);
        }

        $total = $hasFilters
            ? self::cachedFilteredCount($query, $tableName)
            : $tableRows;

        $items = (clone $query)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Total de filas reportado por information_schema. Aproximado pero suficiente
     * para construir paginación. Cacheado en Redis 5min porque el valor cambia poco.
     */
    private static function approximateRowCount(string $tableName): int
    {
        $cacheKey = 'fastpag:rows:'.$tableName;

        return (int) Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tableName) {
            $row = DB::selectOne(
                'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tableName]
            );

            return $row !== null ? (int) ($row->TABLE_ROWS ?? 0) : 0;
        });
    }

    /**
     * COUNT(*) cacheado por hash del SQL+bindings de la query filtrada.
     */
    private static function cachedFilteredCount($query, string $tableName): int
    {
        $sql = method_exists($query, 'toSql') ? $query->toSql() : '';
        $bindings = method_exists($query, 'getBindings') ? $query->getBindings() : [];
        $cacheKey = 'fastpag:count:'.$tableName.':'.md5($sql.serialize($bindings));

        return (int) Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query) {
            return (clone $query)->count();
        });
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function standardPaginate($query, int $perPage, int $page): LengthAwarePaginator
    {
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
