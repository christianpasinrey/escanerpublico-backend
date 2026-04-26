<?php

namespace Modules\Search\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Search\Events\SearchPerformed;

/**
 * Listener desacoplado que registra cada búsqueda federada en el log
 * estructurado. No bloquea la respuesta — vive en el ciclo del evento.
 *
 * Útil para:
 *  - Detectar términos sin resultados (mejora de cobertura).
 *  - Identificar dominios "muertos" (provider que nunca devuelve hits).
 *  - Estimar carga real del endpoint en producción.
 *
 * Si el dispatch del evento crece, basta con registrar más listeners
 * (analytics, caché de queries populares, alertas) sin tocar el servicio.
 */
class LogSearchTelemetry
{
    public function handle(SearchPerformed $event): void
    {
        $r = $event->results;

        Log::channel(config('logging.default'))->info('search.federated', [
            'query' => $r->query,
            'query_length' => mb_strlen($r->query),
            'total_hits' => $r->total_hits,
            'took_ms' => $r->took_ms,
            'buckets' => array_map(
                fn ($b) => ['key' => $b->key, 'hits' => count($b->hits)],
                $r->buckets,
            ),
        ]);
    }
}
