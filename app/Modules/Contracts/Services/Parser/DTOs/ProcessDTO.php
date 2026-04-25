<?php

namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ProcessDTO
{
    public function __construct(
        public ?string $procedure_code,
        public ?string $urgency_code,
        public ?string $submission_method_code,
        public ?string $contracting_system_code,
        public ?string $fecha_disponibilidad_docs,
        public ?string $fecha_presentacion_limite,
        public ?string $hora_presentacion_limite,
    ) {}
}
