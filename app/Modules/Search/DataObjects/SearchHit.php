<?php

namespace Modules\Search\DataObjects;

/**
 * Resultado individual normalizado, agnóstico del dominio.
 *
 * Cada provider mapea sus filas Eloquent a SearchHit. La forma es siempre
 * la misma para que el frontend pueda renderizar cualquier hit con un solo
 * componente sin saber de qué módulo viene.
 *
 * - title:      línea principal — nombre, expediente, título normativo
 * - subtitle:   contexto secundario — organismo, fecha, NIF, importe formateado
 * - url:        ruta del frontend público a la ficha humana
 * - api_url:    ruta de la API REST al recurso JSON
 */
final class SearchHit
{
    /**
     * @param  array<string, scalar|null>  $meta  campos extra opcionales que el
     *                                            frontend puede mostrar como badges
     *                                            (status, year, amount…). No se usan
     *                                            para ranking.
     */
    public function __construct(
        public readonly string $type,
        public readonly string|int $id,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly string $url,
        public readonly string $api_url,
        public readonly array $meta = [],
    ) {}
}
