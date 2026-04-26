<?php

namespace Modules\Search\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Search\DataObjects\SearchResults;

/**
 * Se dispara después de cada búsqueda federada exitosa. No bloquea la respuesta.
 * Cualquier listener puede engancharse para analytics, logging, telemetría
 * o caché — sin que el servicio central conozca a esos consumidores.
 */
class SearchPerformed
{
    use Dispatchable;

    public function __construct(
        public readonly SearchResults $results,
    ) {}
}
