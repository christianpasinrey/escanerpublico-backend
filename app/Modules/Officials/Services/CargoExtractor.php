<?php

namespace Modules\Officials\Services;

/**
 * Extrae nombramientos/ceses individuales del título de una entrada del BOE Sección II.A.
 *
 * Estrategia (tras analizar muestras reales del BOE 2023-2026):
 *
 *   1. Localizar el "anchor" más distintivo: <honorific> <NAME>.
 *      Honorific usual en BOE: "don", "doña", "D.", "D.ª", "Don", "Doña" (mezcla
 *      de minúsculas y mayúsculas — el BOE las usa en minúsculas dentro del cuerpo).
 *   2. Inferir event_type del verbo presente en el título:
 *        - "nombra/designa/acuerda nombrar" → appointment
 *        - "cese/cesa/dispone el cese"      → cessation
 *        - "jubilación/jubila"              → cessation (cesa por jubilación)
 *        - "toma posesión"                  → posession
 *   3. Localizar el cargo: la primera ocurrencia de un cargo conocido (CARGO_KEYWORDS)
 *      en el título.
 *
 * Esto es MÁS ROBUSTO que un único regex monolítico porque el orden cargo↔nombre
 * varía en BOE y los predicados de cargo a veces incluyen subordinadas largas.
 *
 * Patrones colectivos (oposiciones, promociones masivas) se descartan.
 */
class CargoExtractor
{
    /**
     * Lista de prefijos de cargo. Ampliable a medida que aparezcan nuevos.
     */
    private const CARGO_KEYWORDS_LIST = [
        'Director', 'Directora', 'Subdirector', 'Subdirectora',
        'Secretario', 'Secretaria', 'Subsecretario', 'Subsecretaria', 'Vicesecretario', 'Vicesecretaria',
        'Vicepresidente', 'Vicepresidenta', 'Presidente', 'Presidenta',
        'Vocal', 'Comisionado', 'Comisionada', 'Consejero', 'Consejera',
        'Delegado', 'Delegada', 'Subdelegado', 'Subdelegada',
        'Embajador', 'Embajadora', 'Cónsul', 'Consul',
        'Magistrado', 'Magistrada', 'Fiscal',
        'General', 'Coronel', 'Brigada', 'Almirante',
        'Jefe', 'Jefa',
        'Interventor', 'Interventora', 'Tesorero', 'Tesorera',
        'Inspector', 'Inspectora', 'Letrado', 'Letrada',
        'Asesor', 'Asesora', 'Coordinador', 'Coordinadora',
        'Gerente', 'Decano', 'Decana', 'Rector', 'Rectora', 'Vicerrector', 'Vicerrectora',
        'Catedrático', 'Catedrática', 'Profesor', 'Profesora',
        'Notario', 'Notaria', 'Registrador', 'Registradora',
        'Abogado', 'Abogada', 'Procurador', 'Procuradora',
        'Comisario', 'Comisaria',
        'Capitán', 'Teniente', 'Comandante',
        'Subinspector', 'Subinspectora',
        'Adjunto', 'Adjunta',
    ];

    /** Honorific patterns. Aceptan mayúscula o minúscula inicial. */
    private const HONORIFIC_REGEX = '(?:[Dd]\.ª|[Dd]ña\.?|[Dd]oña|[Dd]on|[Dd]\.)';

    /**
     * Pistas de eventos colectivos / no aplicables que descartamos en cuanto los detectemos.
     */
    private const COLLECTIVE_HINTS = [
        'se nombran', 'se promueve a', 'se ascienden', 'se promueven', 'se asciende a',
        'funcionarios de carrera', 'lista de aprobados', 'lista de admitidos',
        'oposiciones libres', 'concurso de méritos', 'concurso general', 'concurso específico',
        'turno de promoción', 'pruebas selectivas', 'proceso selectivo',
        'libre designación', 'puesto de trabajo',
        'catedráticas y catedráticos', 'profesores titulares y catedráticos',
        'personal funcionario', 'plaza vacante',
    ];

    /**
     * @return array{event_type: string, honorific: ?string, full_name: string, cargo: string}|null
     */
    public function extract(?string $titulo): ?array
    {
        if ($titulo === null || trim($titulo) === '') {
            return null;
        }

        $lower = mb_strtolower($titulo, 'UTF-8');
        foreach (self::COLLECTIVE_HINTS as $hint) {
            if (str_contains($lower, mb_strtolower($hint, 'UTF-8'))) {
                return null;
            }
        }

        // Step 1: localizar honorific + name. Sin esto no podemos identificar persona.
        $personMatch = $this->findPersonByHonorific($titulo);
        if ($personMatch === null) {
            return null;
        }

        // Step 2: inferir event_type del verbo del título.
        $eventType = $this->inferEventType($lower);
        if ($eventType === null) {
            return null;
        }

        // Step 3: localizar el primer cargo conocido.
        $cargo = $this->findCargo($titulo);
        if ($cargo === null) {
            return null;
        }

        return [
            'event_type' => $eventType,
            'honorific' => $personMatch['honorific'],
            'full_name' => $personMatch['name'],
            'cargo' => $cargo,
        ];
    }

    /**
     * Busca patrones honorific + name. Devuelve el primer match.
     * Soporta nombres con tildes, ñ, espacios, guiones (apellidos compuestos).
     *
     * @return array{honorific: string, name: string}|null
     */
    private function findPersonByHonorific(string $titulo): ?array
    {
        $hRegex = self::HONORIFIC_REGEX;
        // Negative lookahead: cada palabra siguiente del nombre NO puede empezar con
        // un cargo conocido (para no comerse "Juan Pérez Director General" entero).
        $cargoAhead = implode('|', array_map(fn ($k) => preg_quote($k, '/'), self::CARGO_KEYWORDS_LIST));
        $nameRegex = '[A-ZÁÉÍÓÚÑ][\p{L}\-]+(?:\s+(?!(?:'.$cargoAhead.')\b)[A-ZÁÉÍÓÚÑ][\p{L}\-]+)+';
        $pattern = "/(?P<honorific>{$hRegex})\\s+(?P<name>{$nameRegex})/u";

        if (preg_match($pattern, $titulo, $m) !== 1) {
            return null;
        }

        return [
            'honorific' => rtrim($m['honorific'], '.'),
            'name' => trim($m['name']),
        ];
    }

    /**
     * Detecta el tipo de evento por palabras clave en el título.
     */
    private function inferEventType(string $lowerTitulo): ?string
    {
        if (str_contains($lowerTitulo, 'jubilación') || str_contains($lowerTitulo, 'jubilacion')) {
            return 'cessation';
        }
        if (preg_match('/\b(?:cese|cesa|dispone\s+(?:el\s+)?cese)\b/u', $lowerTitulo) === 1) {
            return 'cessation';
        }
        if (preg_match('/toma(?:\s+de)?\s+posesi[oó]n/u', $lowerTitulo) === 1) {
            return 'posession';
        }
        if (preg_match('/\b(?:nombra|designa|acuerda\s+nombrar)\b/u', $lowerTitulo) === 1) {
            return 'appointment';
        }

        return null;
    }

    /**
     * Localiza el cargo: primera ocurrencia de un cargo conocido + el sintagma asociado.
     * Limita el cargo a un fragmento legible (hasta primer comma/period si excede 80 chars).
     */
    private function findCargo(string $titulo): ?string
    {
        $pipe = implode('|', array_map(fn ($k) => preg_quote($k, '/'), self::CARGO_KEYWORDS_LIST));
        // Buscar la primera ocurrencia + extender hasta coma/period o fin
        $pattern = "/\\b(?P<cargo>(?:{$pipe})(?:\\s+[\\p{L}áéíóúÁÉÍÓÚñÑ\\-,]+){0,8})/u";

        if (preg_match($pattern, $titulo, $m) !== 1) {
            return null;
        }
        $cargo = trim($m['cargo'] ?? '');

        // Limpia trailing comma/punctuation y partículas residuales
        $cargo = preg_replace('/[,.\s]+$/u', '', $cargo) ?? $cargo;
        $cargo = preg_replace('/\s+(con|en|para|de\s+la|del)\s*$/iu', '', $cargo) ?? $cargo;
        // Si el cargo termina en una preposición rara, lo recortamos
        $cargo = preg_replace('/,\s*$/u', '', $cargo) ?? $cargo;

        return $cargo !== '' ? trim($cargo) : null;
    }

    public static function normalize(string $name): string
    {
        $t = mb_strtolower($name, 'UTF-8');
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);
        if ($transliterated !== false) {
            $t = $transliterated;
        }
        $replaced = preg_replace('/[^a-z0-9\s\-]+/', '', $t);
        $t = $replaced ?? $t;
        $collapsed = preg_replace('/\s+/', ' ', $t);

        return trim($collapsed ?? $t);
    }
}
