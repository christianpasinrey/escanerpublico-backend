<?php

namespace Modules\Search\Contracts;

use Modules\Search\DataObjects\SearchBucket;

/**
 * Contrato que cada módulo implementa para participar en la búsqueda federada.
 *
 * El servicio central no conoce a Contract, Organization, LegislationNorm, etc.
 * Solo conoce SearchProvider — cada módulo se autogestiona y registra su provider
 * en el container con `$this->app->tag([X::class], 'search.providers')`.
 *
 * Diseñado para ser puro: una llamada determinista (query, limit) → SearchBucket.
 * Sin estado, sin side effects, sin acoplamiento a HTTP.
 */
interface SearchProvider
{
    /**
     * Identificador estable del bucket — kebab-case singular del dominio.
     * Se usa en la respuesta JSON, en analytics y como i18n key en frontend.
     *
     * Ejemplos: 'contract', 'organization', 'company', 'subsidy-call',
     * 'subsidy-grant', 'legislation', 'official'.
     */
    public function key(): string;

    /**
     * Etiqueta humana del bucket — la que el usuario lee en la columna de
     * resultados. Localizable en el futuro vía traducciones del módulo.
     */
    public function label(): string;

    /**
     * Ejecuta la búsqueda en el dominio del módulo y devuelve el bucket
     * con los hits encontrados. Debe respetar el `$limit` superior — los
     * proveedores que devuelvan más de `$limit` hits serán descartados al
     * llegar al servicio central, así que no merece la pena ignorarlo.
     *
     * `$total` del bucket puede reportar el conteo real (no truncado) si la
     * implementación lo conoce sin coste extra; en caso contrario, devolver
     * count($hits).
     */
    public function search(string $query, int $limit): SearchBucket;
}
