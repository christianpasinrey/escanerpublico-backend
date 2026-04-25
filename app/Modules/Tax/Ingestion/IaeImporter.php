<?php

namespace Modules\Tax\Ingestion;

use Illuminate\Support\Facades\DB;
use Modules\Tax\Models\EconomicActivity;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Importer del Impuesto sobre Actividades Económicas (IAE).
 *
 * Origen primario: Real Decreto Legislativo 1175/1990, de 28 de septiembre, por el que
 * se aprueban las Tarifas y la Instrucción del Impuesto sobre Actividades Económicas
 * (BOE núm. 234 de 29-09-1990, consolidado).
 *
 * Estructura jerárquica del IAE:
 *  - 3 secciones: Sección 1 (Empresariales), Sección 2 (Profesionales), Sección 3 (Artistas).
 *  - Divisiones (1 dígito numérico)
 *  - Agrupaciones (2 dígitos)
 *  - Grupos (3 dígitos)
 *  - Epígrafes (4 dígitos, con sufijo letra a veces: 311.1)
 *
 * El IAE no se publica en formato XLS oficial. Estrategias soportadas:
 *
 *  1. Fixture committeado (PRINCIPAL): JSON consolidado en
 *     `app/Modules/Tax/Ingestion/data/iae_seed.json` con el catálogo manual extraído.
 *
 *  2. Importación HTML (futura/secundaria): parseo del HTML de la sede AEAT
 *     mediante Symfony DomCrawler. La AEAT publica el árbol completo navegable.
 *
 * Idempotente: usa updateOrCreate por (system, code, year).
 */
class IaeImporter
{
    public const SYSTEM = 'iae';

    public const DEFAULT_YEAR = 1992; // Las tarifas aplican desde 1992

    public const VALID_FROM = '1992-01-01';

    /**
     * Importa desde un fixture JSON con estructura:
     *  [
     *    {"code": "1", "section": "1", "level": 1, "name": "..."},
     *    {"code": "11", "section": "1", "level": 2, "name": "...", "parent_code": "1"},
     *    ...
     *  ]
     *
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importFromJson(string $path, int $year = self::DEFAULT_YEAR): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Fixture IAE no accesible: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se pudo leer el fixture IAE.');
        }

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = json_decode($json, true);
        if (! is_array($rows)) {
            throw new RuntimeException('Fixture IAE no es JSON válido.');
        }

        return $this->importFromArray($rows, $year);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importFromArray(array $rows, int $year = self::DEFAULT_YEAR): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $year, &$stats) {
            foreach ($rows as $row) {
                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $level = isset($row['level']) ? (int) $row['level'] : null;
                $section = isset($row['section']) ? (string) $row['section'] : null;
                $parentCode = isset($row['parent_code']) ? (string) $row['parent_code'] : null;

                if ($code === '' || $name === '' || $level === null) {
                    $stats['skipped']++;

                    continue;
                }

                $action = $this->upsert([
                    'system' => self::SYSTEM,
                    'code' => $code,
                    'parent_code' => $parentCode,
                    'level' => $level,
                    'name' => $name,
                    'section' => $section,
                    'year' => $year,
                    'valid_from' => self::VALID_FROM,
                ]);
                $stats[$action]++;
            }
        });

        return $stats;
    }

    /**
     * Importer secundario desde HTML AEAT (best-effort).
     *
     * Útil sólo si se proporciona el HTML del árbol de la sede AEAT.
     * Para el MVP, el camino principal es el fixture JSON.
     *
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importFromHtml(string $html, int $year = self::DEFAULT_YEAR, ?string $section = null): array
    {
        $crawler = new Crawler($html);

        $rows = [];

        // Selector heurístico: buscamos enlaces a códigos IAE bajo nodos de árbol.
        // La AEAT usa típicamente <li><a>{code} {name}</a><ul>...</ul></li>
        $crawler->filter('li')->each(function (Crawler $node) use (&$rows, $section) {
            $text = trim($node->text());
            // Patrón: "1234 Algo" o "1234.5 Algo" donde 1234 es 1-4 dígitos
            if (preg_match('/^(\d{1,4}(?:\.\d+)?)\s+(.+)$/u', $text, $m)) {
                $code = $m[1];
                $name = trim($m[2]);
                $level = $this->detectLevel($code);
                if ($level !== null) {
                    $rows[] = [
                        'code' => $code,
                        'name' => $name,
                        'level' => $level,
                        'parent_code' => $this->parentCode($code, $level),
                        'section' => $section ?? $this->guessSection($code),
                    ];
                }
            }
        });

        return $this->importFromArray($rows, $year);
    }

    private function detectLevel(string $code): ?int
    {
        // El IAE permite 1, 2, 3 o 4 dígitos (epígrafe), opcionalmente con .X
        $base = strtok($code, '.') ?: $code;
        $len = strlen($base);

        return match (true) {
            $len === 1 && ctype_digit($base) => 1, // división
            $len === 2 && ctype_digit($base) => 2, // agrupación
            $len === 3 && ctype_digit($base) => 3, // grupo
            $len === 4 && ctype_digit($base) => 4, // epígrafe
            default => null,
        };
    }

    private function parentCode(string $code, int $level): ?string
    {
        $base = strtok($code, '.') ?: $code;

        return match ($level) {
            1 => null,
            2, 3, 4 => substr($base, 0, $level - 1),
            default => null,
        };
    }

    private function guessSection(string $code): ?string
    {
        // La sección IAE se determina por el contexto del documento, no del código.
        // En ausencia de información, devolvemos null.
        return null;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return 'inserted'|'updated'|'skipped'
     */
    private function upsert(array $attrs): string
    {
        $existing = EconomicActivity::query()
            ->where('system', $attrs['system'])
            ->where('code', $attrs['code'])
            ->where('year', $attrs['year'])
            ->first();

        if ($existing === null) {
            EconomicActivity::query()->create($attrs);

            return 'inserted';
        }

        $changed = false;
        foreach (['parent_code', 'level', 'name', 'section'] as $field) {
            if ((string) ($existing->{$field} ?? '') !== (string) ($attrs[$field] ?? '')) {
                $changed = true;
                break;
            }
        }
        $existingValidFrom = $existing->valid_from?->format('Y-m-d');
        if ($existingValidFrom !== ($attrs['valid_from'] ?? null)) {
            $changed = true;
        }

        if (! $changed) {
            return 'skipped';
        }

        $existing->fill($attrs);
        $existing->save();

        return 'updated';
    }
}
