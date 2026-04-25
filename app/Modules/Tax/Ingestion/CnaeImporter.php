<?php

namespace Modules\Tax\Ingestion;

use Illuminate\Support\Facades\DB;
use Modules\Tax\Models\EconomicActivity;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Importer de la **Clasificación Nacional de Actividades Económicas 2025 (CNAE-2025)**.
 *
 * Origen: Real Decreto 10/2025, de 14 de enero, por el que se aprueba la CNAE-2025
 *         (BOE núm. 14 de 16-01-2025). Tabla descargable como XLS desde la web del INE.
 *
 * Formato CNAE-2025:
 *  - 21 secciones (letras A-U)
 *  - 88 divisiones (2 dígitos)
 *  - ~272 grupos (3 dígitos)
 *  - ~615 clases (4 dígitos)
 *  - ~~860 subclases (5 dígitos, primera vez en la CNAE española)
 *
 * El XLS oficial contiene 5 columnas relevantes:
 *  - Código (string variable: "A", "01", "011", "0111", "01110")
 *  - Título (descripción)
 *  - + a veces metadatos adicionales (notas, vínculos a CNAE-2009, etc.)
 *
 * Estrategia:
 *  - Lee la primera hoja del workbook.
 *  - Busca la columna "Código" y "Título" por cabecera (case-insensitive).
 *  - Detecta el nivel por longitud del código:
 *      letra (1 char) → sección (level 1)
 *      2 dígitos      → división (level 2)
 *      3 dígitos      → grupo (level 3)
 *      4 dígitos      → clase (level 4)
 *      5 dígitos      → subclase (level 5)
 *  - Calcula parent_code truncando: "01110" → parent "0111", "0111" → "011", "011" → "01", "01" → "A".
 *  - Idempotente: usa updateOrCreate por (system, code, year).
 *
 * Si el XLS no existe en disco, se acepta un fixture committeado en tests/fixtures/tax/cnae2025_sample.xlsx
 * para tests automáticos.
 */
class CnaeImporter
{
    public const SYSTEM = 'cnae';

    public const YEAR = 2025;

    public const VALID_FROM = '2025-01-15';

    /**
     * Mapeo letra → letra de sección sobre las divisiones (puro RD 10/2025).
     *
     * @var array<int, string> divisiónCode (int) → sección (letra)
     */
    private const DIVISION_TO_SECTION = [
        1 => 'A', 2 => 'A', 3 => 'A',
        5 => 'B', 6 => 'B', 7 => 'B', 8 => 'B', 9 => 'B',
        10 => 'C', 11 => 'C', 12 => 'C', 13 => 'C', 14 => 'C', 15 => 'C', 16 => 'C',
        17 => 'C', 18 => 'C', 19 => 'C', 20 => 'C', 21 => 'C', 22 => 'C', 23 => 'C',
        24 => 'C', 25 => 'C', 26 => 'C', 27 => 'C', 28 => 'C', 29 => 'C', 30 => 'C',
        31 => 'C', 32 => 'C', 33 => 'C',
        35 => 'D',
        36 => 'E', 37 => 'E', 38 => 'E', 39 => 'E',
        41 => 'F', 42 => 'F', 43 => 'F',
        45 => 'G', 46 => 'G', 47 => 'G',
        49 => 'H', 50 => 'H', 51 => 'H', 52 => 'H', 53 => 'H',
        55 => 'I', 56 => 'I',
        58 => 'J', 59 => 'J', 60 => 'J', 61 => 'J', 62 => 'J', 63 => 'J',
        64 => 'K', 65 => 'K', 66 => 'K',
        68 => 'L',
        69 => 'M', 70 => 'M', 71 => 'M', 72 => 'M', 73 => 'M', 74 => 'M', 75 => 'M',
        77 => 'N', 78 => 'N', 79 => 'N', 80 => 'N', 81 => 'N', 82 => 'N',
        84 => 'O',
        85 => 'P',
        86 => 'Q', 87 => 'Q', 88 => 'Q',
        90 => 'R', 91 => 'R', 92 => 'R', 93 => 'R',
        94 => 'S', 95 => 'S', 96 => 'S',
        97 => 'T', 98 => 'T',
        99 => 'U',
    ];

    /**
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importFromXlsx(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Fichero CNAE no accesible: {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === []) {
            throw new RuntimeException('XLS vacío.');
        }

        // Detección de cabecera (puede no estar en la fila 0)
        [$headerRowIdx, $codeCol, $titleCol] = $this->detectHeader($rows);

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $headerRowIdx, $codeCol, $titleCol, &$stats) {
            $lineCount = count($rows);
            for ($i = $headerRowIdx + 1; $i < $lineCount; $i++) {
                $row = $rows[$i] ?? null;
                if ($row === null) {
                    continue;
                }

                $code = $this->normalizeCode($row[$codeCol] ?? null);
                $title = $this->normalizeTitle($row[$titleCol] ?? null);
                if ($code === '' || $title === '') {
                    $stats['skipped']++;

                    continue;
                }

                $level = $this->detectLevel($code);
                if ($level === null) {
                    $stats['skipped']++;

                    continue;
                }

                $parent = $this->parentCode($code, $level);
                $section = $this->sectionFor($code, $level);

                $action = $this->upsert([
                    'system' => self::SYSTEM,
                    'code' => $code,
                    'parent_code' => $parent,
                    'level' => $level,
                    'name' => $title,
                    'section' => $section,
                    'year' => self::YEAR,
                    'valid_from' => self::VALID_FROM,
                ]);
                $stats[$action]++;
            }
        });

        return $stats;
    }

    /**
     * @return array{inserted: int, updated: int, skipped: int}
     */
    public function importFromArray(array $rows, int $year = self::YEAR, string $validFrom = self::VALID_FROM): array
    {
        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $year, $validFrom, &$stats) {
            foreach ($rows as $row) {
                $code = $this->normalizeCode($row['code'] ?? null);
                $title = $this->normalizeTitle($row['name'] ?? $row['title'] ?? null);
                if ($code === '' || $title === '') {
                    $stats['skipped']++;

                    continue;
                }
                $level = $this->detectLevel($code);
                if ($level === null) {
                    $stats['skipped']++;

                    continue;
                }
                $parent = $this->parentCode($code, $level);
                $section = $this->sectionFor($code, $level);

                $action = $this->upsert([
                    'system' => self::SYSTEM,
                    'code' => $code,
                    'parent_code' => $parent,
                    'level' => $level,
                    'name' => $title,
                    'section' => $section,
                    'year' => $year,
                    'valid_from' => $validFrom,
                ]);
                $stats[$action]++;
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: int, 1: int, 2: int} [headerRowIndex, codeColIndex, titleColIndex]
     */
    private function detectHeader(array $rows): array
    {
        $maxRows = min(20, count($rows));
        for ($r = 0; $r < $maxRows; $r++) {
            $row = $rows[$r] ?? [];
            $codeCol = null;
            $titleCol = null;
            foreach ($row as $colIdx => $value) {
                if (! is_string($value)) {
                    continue;
                }
                $norm = mb_strtolower(trim($value));
                if (str_contains($norm, 'cód') || $norm === 'codigo' || $norm === 'code') {
                    $codeCol = $colIdx;
                }
                if (str_contains($norm, 'título') || str_contains($norm, 'titulo')
                    || str_contains($norm, 'descrip') || $norm === 'name' || $norm === 'nombre') {
                    $titleCol = $colIdx;
                }
            }

            if ($codeCol !== null && $titleCol !== null) {
                return [$r, $codeCol, $titleCol];
            }
        }

        // Fallback: asumir cols 0 y 1
        return [-1, 0, 1];
    }

    private function normalizeCode(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        // Excel suele devolver dígitos como números; preservamos el zero-padding
        if (is_numeric($value)) {
            $asString = (string) $value;

            // No conocemos el padding original (ej: 011 vs 11). Si el número tiene < 4 dígitos
            // y empezaba por 0 lo perdemos. Aceptamos el formato textual del XLS sin padding adicional.
            return trim($asString);
        }

        return strtoupper(trim((string) $value));
    }

    private function normalizeTitle(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Detecta el nivel jerárquico CNAE por la longitud del código:
     *  1 → sección (1 char letra A-U)
     *  2 → división (2 dígitos)
     *  3 → grupo (3 dígitos)
     *  4 → clase (4 dígitos)
     *  5 → subclase (5 dígitos)
     */
    private function detectLevel(string $code): ?int
    {
        $len = strlen($code);

        if ($len === 1 && ctype_alpha($code)) {
            return 1;
        }
        if ($len === 2 && ctype_digit($code)) {
            return 2;
        }
        if ($len === 3 && ctype_digit($code)) {
            return 3;
        }
        if ($len === 4 && ctype_digit($code)) {
            return 4;
        }
        if ($len === 5 && ctype_digit($code)) {
            return 5;
        }

        return null;
    }

    private function parentCode(string $code, int $level): ?string
    {
        return match ($level) {
            1 => null,
            2 => $this->sectionFor($code, 2),
            3 => substr($code, 0, 2),
            4 => substr($code, 0, 3),
            5 => substr($code, 0, 4),
            default => null,
        };
    }

    private function sectionFor(string $code, int $level): ?string
    {
        if ($level === 1) {
            return $code;
        }
        $division = (int) substr($code, 0, 2);

        return self::DIVISION_TO_SECTION[$division] ?? null;
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
        // Comparar valid_from como string normalizado YYYY-MM-DD
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
