<?php

namespace Modules\Officials\Services;

/**
 * Extrae nombramientos/ceses individuales del título de una entrada del BOE Sección II.A.
 *
 * Patrones tras analizar 100+ entradas reales del BOE:
 *
 *   "se nombra a Don Juan Pérez García Director General de Tributos."
 *   "se nombra a Doña María López Sánchez Subsecretaria de Hacienda."
 *   "el cese de Don Antonio Ruiz como Director General de Patrimonio."
 *   "cesa Doña Ana Sánchez como Subsecretaria de Industria."
 *   "se dispone el cese de D. José Pérez como Director."
 *
 * Devuelve null si:
 *   - El título no encaja con ninguno de los patrones de nombramiento individual.
 *   - Es un nombramiento colectivo ("se nombran funcionarios", "se promueve a...").
 *   - Es una resolución administrativa que no nombra a una persona física concreta.
 */
class CargoExtractor
{
    /**
     * Anchor de cargos conocidos. El name regex puede ser greedy hasta encontrar
     * una de estas palabras (que casi siempre prefijan el cargo en BOE Sección II.A).
     */
    private const CARGO_KEYWORDS = '(?:Director|Directora|Subdirector|Subdirectora|Secretari[oa]|Subsecretari[oa]|Vicesecretari[oa]|Vicepresident[ea]|Presidente|Presidenta|Vocal|Comisionad[oa]|Consejer[oa]|Delegad[oa]|Embajador|Embajadora|C[óo]nsul|Magistrad[oa]|Fiscal|General|Coronel|Brigada|Almirante|Jefe|Jefa|Interventor|Interventora|Tesorer[oa]|Inspector|Inspectora|Letrad[oa]|Asesor|Asesora|Coordinador|Coordinadora|Gerente|Decano|Decana|Rector|Rectora)';

    /** @var array<string, string> */
    private array $patterns;

    public function __construct()
    {
        $cargo = '(?P<cargo>'.self::CARGO_KEYWORDS.'[^.]*?)';
        $h = '(?P<honorific>D\.|D\.ª|Don|Doña|Dña\.?)';
        $n = '(?P<name>[A-ZÁÉÍÓÚÑ][\p{L}\s\-]+?)';

        $this->patterns = [
            'appointment' => "/se\\s+nombra\\s+a\\s+{$h}\\s+{$n}\\s+(?:como\\s+)?{$cargo}[.,]/u",
            'cessation' => "/(?:el\\s+cese\\s+de|cesa)\\s+{$h}\\s+{$n}\\s+(?:como\\s+)?{$cargo}[.,]/u",
            'posession' => "/toma(?:\\s+de)?\\s+posesi[oó]n\\s+{$h}\\s+{$n}\\s+(?:como\\s+)?{$cargo}[.,]/u",
        ];
    }

    /** @var array<int, string> */
    private const COLLECTIVE_HINTS = [
        'se nombran',
        'se promueve a',
        'se ascienden',
        'funcionarios de carrera',
        'lista de aprobados',
        'oposiciones libres',
        'concurso de méritos',
        'turno de promoción',
    ];

    /**
     * @return array{event_type: string, honorific: string, full_name: string, cargo: string}|null
     */
    public function extract(?string $titulo): ?array
    {
        if ($titulo === null || trim($titulo) === '') {
            return null;
        }

        $lower = mb_strtolower($titulo, 'UTF-8');
        foreach (self::COLLECTIVE_HINTS as $hint) {
            if (str_contains($lower, $hint)) {
                return null;
            }
        }

        foreach ($this->patterns as $eventType => $pattern) {
            if (preg_match($pattern, $titulo, $m) === 1) {
                $name = trim($m['name']);
                $cargo = $this->cleanCargo($m['cargo']);
                if ($name === '' || $cargo === '') {
                    continue;
                }
                if (mb_strlen($name) < 4 || mb_strlen($name) > 100) {
                    continue;
                }

                return [
                    'event_type' => $eventType,
                    'honorific' => trim(rtrim($m['honorific'], '.')),
                    'full_name' => $this->cleanName($name),
                    'cargo' => $cargo,
                ];
            }
        }

        return null;
    }

    /**
     * Normaliza un nombre para uso como clave de identidad: minúsculas, sin tildes,
     * sin espacios extra. Misma persona = misma normalización.
     */
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

    private function cleanName(string $name): string
    {
        // Elimina sufijos comunes que se cuelan con el regex avaricioso
        $name = preg_replace('/\s+(como|para)$/iu', '', $name) ?? $name;

        return trim($name);
    }

    private function cleanCargo(string $cargo): string
    {
        // Elimina prefijos espurios y palabras finales que no son parte del cargo
        $cargo = preg_replace('/^(como\s+|por\s+)/iu', '', $cargo) ?? $cargo;

        return trim($cargo);
    }
}
