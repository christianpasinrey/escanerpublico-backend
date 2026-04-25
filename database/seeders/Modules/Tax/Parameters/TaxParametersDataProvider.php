<?php

namespace Database\Seeders\Modules\Tax\Parameters;

/**
 * Repositorio centralizado de datasets fiscales 2023-2026.
 *
 * Cada método devuelve un array listo para insertar en la BD.
 * Los valores numéricos están citados con su fuente BOE/DOG/DOGC/BOJA en
 * source_url o en notes. Cuando un dato no varía año a año, se factoriza aquí.
 */
class TaxParametersDataProvider
{
    public const BOE_LEY_35_2006 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764';

    public const BOE_RD_439_2007 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820';

    public const BOE_LEY_37_1992 = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740';

    public const BOE_RDLEY_13_2022 = 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-12482';

    /**
     * Escala estatal IRPF — Ley 35/2006 art. 63.
     * Estructura constante 2023→2026: 5 tramos (en 2024 se añade el 6º, top 24.5%).
     * Para 2023 solo hay 5 tramos con tipo top 22.5 % (sobre lo que excede 300 k).
     *
     * Fuente: Ley 35/2006 art. 63 (escala general estatal),
     * modificada por Ley 31/2022 (añade tramo top 24.5 %) y Ley 38/2022.
     *
     * @return list<array<string,mixed>>
     */
    public static function stateIrpfBrackets(int $year): array
    {
        // 2023: 5 tramos, top 22.5 %
        if ($year === 2023) {
            return [
                ['from_amount' => 0, 'to_amount' => 12450, 'rate' => 9.5, 'fixed_amount' => 0,
                    'source_url' => self::BOE_LEY_35_2006],
                ['from_amount' => 12450, 'to_amount' => 20200, 'rate' => 12.0, 'fixed_amount' => 1182.75,
                    'source_url' => self::BOE_LEY_35_2006],
                ['from_amount' => 20200, 'to_amount' => 35200, 'rate' => 15.0, 'fixed_amount' => 2112.75,
                    'source_url' => self::BOE_LEY_35_2006],
                ['from_amount' => 35200, 'to_amount' => 60000, 'rate' => 18.5, 'fixed_amount' => 4362.75,
                    'source_url' => self::BOE_LEY_35_2006],
                ['from_amount' => 60000, 'to_amount' => 300000, 'rate' => 22.5, 'fixed_amount' => 8950.75,
                    'source_url' => self::BOE_LEY_35_2006],
                ['from_amount' => 300000, 'to_amount' => null, 'rate' => 24.5, 'fixed_amount' => 62950.75,
                    'source_url' => self::BOE_LEY_35_2006],
            ];
        }

        // 2024+: idem 6 tramos. Ley 31/2022 (BOE-A-2022-22128) reformó art. 63.
        return [
            ['from_amount' => 0, 'to_amount' => 12450, 'rate' => 9.5, 'fixed_amount' => 0,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 12450, 'to_amount' => 20200, 'rate' => 12.0, 'fixed_amount' => 1182.75,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 20200, 'to_amount' => 35200, 'rate' => 15.0, 'fixed_amount' => 2112.75,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 35200, 'to_amount' => 60000, 'rate' => 18.5, 'fixed_amount' => 4362.75,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 60000, 'to_amount' => 300000, 'rate' => 22.5, 'fixed_amount' => 8950.75,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 300000, 'to_amount' => null, 'rate' => 24.5, 'fixed_amount' => 62950.75,
                'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
        ];
    }

    /**
     * Escala del ahorro estatal (rendimientos del capital + ganancias).
     * Tipos consolidados Ley 31/2022 (vigente 2023→). 2023 añadió tramos 27/28 %.
     *
     * @return list<array<string,mixed>>
     */
    public static function savingsIrpfBrackets(int $year): array
    {
        if ($year === 2023) {
            return [
                ['from_amount' => 0, 'to_amount' => 6000, 'rate' => 19.0, 'fixed_amount' => 0,
                    'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
                ['from_amount' => 6000, 'to_amount' => 50000, 'rate' => 21.0, 'fixed_amount' => 1140,
                    'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
                ['from_amount' => 50000, 'to_amount' => 200000, 'rate' => 23.0, 'fixed_amount' => 10380,
                    'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
                ['from_amount' => 200000, 'to_amount' => 300000, 'rate' => 27.0, 'fixed_amount' => 44880,
                    'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
                ['from_amount' => 300000, 'to_amount' => null, 'rate' => 28.0, 'fixed_amount' => 71880,
                    'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128'],
            ];
        }

        // 2024+: misma escala (no hay reforma específica para el ahorro en 2024-2026).
        return [
            ['from_amount' => 0, 'to_amount' => 6000, 'rate' => 19.0, 'fixed_amount' => 0,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 6000, 'to_amount' => 50000, 'rate' => 21.0, 'fixed_amount' => 1140,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 50000, 'to_amount' => 200000, 'rate' => 23.0, 'fixed_amount' => 10380,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 200000, 'to_amount' => 300000, 'rate' => 27.0, 'fixed_amount' => 44880,
                'source_url' => self::BOE_LEY_35_2006],
            ['from_amount' => 300000, 'to_amount' => null, 'rate' => 28.0, 'fixed_amount' => 71880,
                'source_url' => self::BOE_LEY_35_2006],
        ];
    }

    /**
     * Escalas autonómicas IRPF para MD, CT, AN, VC.
     * Cada CCAA con su Ley de Presupuestos / Ley específica.
     *
     * Las escalas autonómicas se aplican sobre la misma base liquidable
     * que la estatal y producen una cuota autonómica que se suma a la estatal.
     *
     * @return array<string, list<array<string,mixed>>>
     */
    public static function regionalIrpfBrackets(int $year): array
    {
        return [
            'MD' => self::madridBrackets($year),
            'CT' => self::cataloniaBrackets($year),
            'AN' => self::andaluciaBrackets($year),
            'VC' => self::valenciaBrackets($year),
        ];
    }

    /**
     * Madrid — Ley 7/2024 (deflactación 2024) y Ley 7/2022 previa.
     * Tras la deflactación del 4 % de 2024 los tramos cambian; 2025 mantiene.
     *
     * @return list<array<string,mixed>>
     */
    private static function madridBrackets(int $year): array
    {
        // 2023: Ley 13/2022 CAM (texto refundido)
        if ($year === 2023) {
            return [
                ['from_amount' => 0, 'to_amount' => 13362.22, 'rate' => 8.50, 'fixed_amount' => 0,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12162'],
                ['from_amount' => 13362.22, 'to_amount' => 19004.63, 'rate' => 10.70, 'fixed_amount' => 1135.79,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12162'],
                ['from_amount' => 19004.63, 'to_amount' => 35425.68, 'rate' => 12.80, 'fixed_amount' => 1739.53,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12162'],
                ['from_amount' => 35425.68, 'to_amount' => 57320.40, 'rate' => 17.40, 'fixed_amount' => 3841.43,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12162'],
                ['from_amount' => 57320.40, 'to_amount' => null, 'rate' => 20.50, 'fixed_amount' => 7651.27,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12162'],
            ];
        }

        // 2024 deflactación 4% — Ley 1/2024 CAM (BOCM-A-2024)
        if ($year === 2024) {
            return [
                ['from_amount' => 0, 'to_amount' => 13896.71, 'rate' => 8.50, 'fixed_amount' => 0,
                    'source_url' => 'https://www.bocm.es/boletin/CM_Orden_BOCM/2024/01/12/BOCM-20240112-1.PDF'],
                ['from_amount' => 13896.71, 'to_amount' => 19764.81, 'rate' => 10.70, 'fixed_amount' => 1181.22,
                    'source_url' => 'https://www.bocm.es/boletin/CM_Orden_BOCM/2024/01/12/BOCM-20240112-1.PDF'],
                ['from_amount' => 19764.81, 'to_amount' => 36842.71, 'rate' => 12.80, 'fixed_amount' => 1809.11,
                    'source_url' => 'https://www.bocm.es/boletin/CM_Orden_BOCM/2024/01/12/BOCM-20240112-1.PDF'],
                ['from_amount' => 36842.71, 'to_amount' => 59613.22, 'rate' => 17.40, 'fixed_amount' => 3995.08,
                    'source_url' => 'https://www.bocm.es/boletin/CM_Orden_BOCM/2024/01/12/BOCM-20240112-1.PDF'],
                ['from_amount' => 59613.22, 'to_amount' => null, 'rate' => 20.50, 'fixed_amount' => 7957.13,
                    'source_url' => 'https://www.bocm.es/boletin/CM_Orden_BOCM/2024/01/12/BOCM-20240112-1.PDF'],
            ];
        }

        // 2025 y 2026 — mantenemos la deflactación de 2024.
        return [
            ['from_amount' => 0, 'to_amount' => 13896.71, 'rate' => 8.50, 'fixed_amount' => 0,
                'source_url' => 'https://www.bocm.es/'],
            ['from_amount' => 13896.71, 'to_amount' => 19764.81, 'rate' => 10.70, 'fixed_amount' => 1181.22,
                'source_url' => 'https://www.bocm.es/'],
            ['from_amount' => 19764.81, 'to_amount' => 36842.71, 'rate' => 12.80, 'fixed_amount' => 1809.11,
                'source_url' => 'https://www.bocm.es/'],
            ['from_amount' => 36842.71, 'to_amount' => 59613.22, 'rate' => 17.40, 'fixed_amount' => 3995.08,
                'source_url' => 'https://www.bocm.es/'],
            ['from_amount' => 59613.22, 'to_amount' => null, 'rate' => 20.50, 'fixed_amount' => 7957.13,
                'source_url' => 'https://www.bocm.es/'],
        ];
    }

    /**
     * Cataluña — Ley 5/2020 + Ley 12/2023 (modificación tramos).
     * Tipos top más altos que la media (subida 2020 con 9 tramos).
     *
     * @return list<array<string,mixed>>
     */
    private static function cataloniaBrackets(int $year): array
    {
        if ($year === 2023) {
            // Pre-reforma 2023: Ley 5/2020 — 9 tramos
            return [
                ['from_amount' => 0, 'to_amount' => 12450, 'rate' => 10.50, 'fixed_amount' => 0,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 12450, 'to_amount' => 17707.20, 'rate' => 12.00, 'fixed_amount' => 1307.25,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 17707.20, 'to_amount' => 21000, 'rate' => 14.00, 'fixed_amount' => 1938.12,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 21000, 'to_amount' => 33007.20, 'rate' => 15.00, 'fixed_amount' => 2399.13,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 33007.20, 'to_amount' => 53407.20, 'rate' => 18.80, 'fixed_amount' => 4200.21,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 53407.20, 'to_amount' => 90000, 'rate' => 21.50, 'fixed_amount' => 8035.41,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 90000, 'to_amount' => 120000, 'rate' => 23.50, 'fixed_amount' => 15903.13,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 120000, 'to_amount' => 175000, 'rate' => 24.50, 'fixed_amount' => 22953.13,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
                ['from_amount' => 175000, 'to_amount' => null, 'rate' => 25.50, 'fixed_amount' => 36428.13,
                    'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-5587'],
            ];
        }

        // 2024+: tras deflactación parcial 2024. Mantenemos misma estructura.
        return [
            ['from_amount' => 0, 'to_amount' => 12450, 'rate' => 10.50, 'fixed_amount' => 0,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 12450, 'to_amount' => 17707.20, 'rate' => 12.00, 'fixed_amount' => 1307.25,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 17707.20, 'to_amount' => 21000, 'rate' => 14.00, 'fixed_amount' => 1938.12,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 21000, 'to_amount' => 33007.20, 'rate' => 15.00, 'fixed_amount' => 2399.13,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 33007.20, 'to_amount' => 53407.20, 'rate' => 18.80, 'fixed_amount' => 4200.21,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 53407.20, 'to_amount' => 90000, 'rate' => 21.50, 'fixed_amount' => 8035.41,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 90000, 'to_amount' => 120000, 'rate' => 23.50, 'fixed_amount' => 15903.13,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 120000, 'to_amount' => 175000, 'rate' => 24.50, 'fixed_amount' => 22953.13,
                'source_url' => 'https://dogc.gencat.cat/'],
            ['from_amount' => 175000, 'to_amount' => null, 'rate' => 25.50, 'fixed_amount' => 36428.13,
                'source_url' => 'https://dogc.gencat.cat/'],
        ];
    }

    /**
     * Andalucía — Ley 5/2021 + Ley 7/2022 (rebaja generalizada).
     * Estructura post-rebaja 2023.
     *
     * @return list<array<string,mixed>>
     */
    private static function andaluciaBrackets(int $year): array
    {
        // Estructura común 2023→2026 tras la rebaja de la Ley 7/2022 BOJA.
        $rows = [
            ['from_amount' => 0, 'to_amount' => 13000, 'rate' => 9.50, 'fixed_amount' => 0],
            ['from_amount' => 13000, 'to_amount' => 21000, 'rate' => 12.00, 'fixed_amount' => 1235.00],
            ['from_amount' => 21000, 'to_amount' => 35200, 'rate' => 15.00, 'fixed_amount' => 2195.00],
            ['from_amount' => 35200, 'to_amount' => 50000, 'rate' => 18.50, 'fixed_amount' => 4325.00],
            ['from_amount' => 50000, 'to_amount' => 60000, 'rate' => 19.00, 'fixed_amount' => 7063.00],
            ['from_amount' => 60000, 'to_amount' => 120000, 'rate' => 22.50, 'fixed_amount' => 8963.00],
            ['from_amount' => 120000, 'to_amount' => null, 'rate' => 22.50, 'fixed_amount' => 22463.00],
        ];

        $sourceUrl = 'https://www.juntadeandalucia.es/boja/2022/200/1';
        if ($year >= 2024) {
            $sourceUrl = 'https://www.juntadeandalucia.es/boja/';
        }

        return array_map(static fn (array $row) => array_merge($row, ['source_url' => $sourceUrl]), $rows);
    }

    /**
     * Comunidad Valenciana — Ley 7/2014 + Ley 9/2022 (modificación 2023).
     * Tras la reforma 2023 hay 9 tramos (escala muy progresiva).
     *
     * @return list<array<string,mixed>>
     */
    private static function valenciaBrackets(int $year): array
    {
        // Misma estructura 2023→2026 tras Ley 9/2022 GVA (DOGV).
        $rows = [
            ['from_amount' => 0, 'to_amount' => 12000, 'rate' => 9.00, 'fixed_amount' => 0],
            ['from_amount' => 12000, 'to_amount' => 22000, 'rate' => 12.00, 'fixed_amount' => 1080.00],
            ['from_amount' => 22000, 'to_amount' => 32000, 'rate' => 15.00, 'fixed_amount' => 2280.00],
            ['from_amount' => 32000, 'to_amount' => 42000, 'rate' => 17.50, 'fixed_amount' => 3780.00],
            ['from_amount' => 42000, 'to_amount' => 52000, 'rate' => 20.00, 'fixed_amount' => 5530.00],
            ['from_amount' => 52000, 'to_amount' => 65000, 'rate' => 22.50, 'fixed_amount' => 7530.00],
            ['from_amount' => 65000, 'to_amount' => 80000, 'rate' => 25.00, 'fixed_amount' => 10455.00],
            ['from_amount' => 80000, 'to_amount' => 120000, 'rate' => 27.50, 'fixed_amount' => 14205.00],
            ['from_amount' => 120000, 'to_amount' => null, 'rate' => 29.50, 'fixed_amount' => 25205.00],
        ];

        return array_map(
            static fn (array $row) => array_merge($row, [
                'source_url' => 'https://dogv.gva.es/',
            ]),
            $rows,
        );
    }

    /**
     * Mínimos personales y familiares + retenciones por defecto + escalas auxiliares.
     *
     * Importes Ley 35/2006 art. 56-61 (no varían 2023-2026 salvo deflactaciones puntuales).
     *
     * @return list<array<string,mixed>>
     */
    public static function commonParameters(int $year): array
    {
        $boeIrpf = self::BOE_LEY_35_2006;

        return [
            // Mínimo personal y familiar
            ['key' => 'irpf.minimo_personal_general', 'value' => 5550, 'source_url' => $boeIrpf,
                'notes' => 'Mínimo personal Ley 35/2006 art. 57'],
            ['key' => 'irpf.minimo_personal_mayor_65', 'value' => 1150, 'source_url' => $boeIrpf,
                'notes' => 'Incremento mínimo personal mayor de 65 años'],
            ['key' => 'irpf.minimo_personal_mayor_75', 'value' => 1400, 'source_url' => $boeIrpf,
                'notes' => 'Incremento adicional mayor de 75 años'],

            // Mínimo descendientes Ley 35/2006 art. 58
            ['key' => 'irpf.minimo_descendiente.primero', 'value' => 2400, 'source_url' => $boeIrpf,
                'notes' => 'Primer descendiente'],
            ['key' => 'irpf.minimo_descendiente.segundo', 'value' => 2700, 'source_url' => $boeIrpf,
                'notes' => 'Segundo descendiente'],
            ['key' => 'irpf.minimo_descendiente.tercero', 'value' => 4000, 'source_url' => $boeIrpf,
                'notes' => 'Tercer descendiente'],
            ['key' => 'irpf.minimo_descendiente.cuarto_y_siguientes', 'value' => 4500, 'source_url' => $boeIrpf,
                'notes' => 'Cuarto y siguientes descendientes'],
            ['key' => 'irpf.minimo_descendiente.menor_3_anios', 'value' => 2800, 'source_url' => $boeIrpf,
                'notes' => 'Incremento si descendiente menor de 3 años'],

            // Mínimo ascendientes Ley 35/2006 art. 59
            ['key' => 'irpf.minimo_ascendiente_mayor_65', 'value' => 1150, 'source_url' => $boeIrpf,
                'notes' => 'Mínimo ascendientes mayores de 65 o discapacidad'],
            ['key' => 'irpf.minimo_ascendiente_mayor_75', 'value' => 1400, 'source_url' => $boeIrpf,
                'notes' => 'Incremento adicional ascendiente mayor de 75'],

            // Discapacidad Ley 35/2006 art. 60
            ['key' => 'irpf.minimo_discapacidad_general', 'value' => 3000, 'source_url' => $boeIrpf,
                'notes' => 'Mínimo discapacidad < 65 % grado'],
            ['key' => 'irpf.minimo_discapacidad_grave', 'value' => 9000, 'source_url' => $boeIrpf,
                'notes' => 'Mínimo discapacidad ≥ 65 % grado o movilidad reducida'],
            ['key' => 'irpf.minimo_discapacidad_asistencia', 'value' => 3000, 'source_url' => $boeIrpf,
                'notes' => 'Incremento por gastos de asistencia'],

            // Retenciones — RD 439/2007 (Reglamento IRPF)
            ['key' => 'irpf.tipo_retencion_administradores',
                'value' => ['general' => 35.0, 'reducido_pyme' => 19.0, 'umbral_volumen' => 100000],
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Art. 101.2 LIRPF: 35 % general, 19 % si rendimientos netos < 100k en entidades < 100k facturación'],
            ['key' => 'irpf.tipo_retencion_actividades_profesionales',
                'value' => ['general' => 15.0, 'inicio' => 7.0],
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Art. 101.5 LIRPF + RD 439/2007 art. 95: 15 % general, 7 % nuevos profesionales (3 ejercicios)'],
            ['key' => 'irpf.tipo_retencion_capital_mobiliario', 'value' => 19.0,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Art. 101.4 LIRPF: 19 % capital mobiliario'],
            ['key' => 'irpf.tipo_retencion_alquiler', 'value' => 19.0,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Art. 100 RD 439/2007: 19 % retención alquiler inmuebles urbanos'],
            ['key' => 'irpf.tipo_retencion_premios', 'value' => 19.0,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Premios: 19 % retención'],

            // Reducción por rendimientos del trabajo Ley 35/2006 art. 20
            ['key' => 'irpf.reduccion_rendimientos_trabajo',
                'value' => self::reduccionTrabajo($year),
                'source_url' => $boeIrpf,
                'notes' => 'Reducción rendimientos del trabajo (Ley 35/2006 art. 20)'],

            // Escala del ahorro como JSON (referencia)
            ['key' => 'irpf.escala_ahorro_json',
                'value' => self::savingsIrpfBrackets($year),
                'source_url' => $boeIrpf,
                'notes' => 'Snapshot JSON de la escala del ahorro (idéntico contenido a tax_brackets type=irpf_ahorro)'],

            // Tope SS / parámetros laborales
            ['key' => 'ss.tope_max_base_anual',
                'value' => self::topeMaxBaseAnual($year),
                'source_url' => self::ssOrden($year),
                'notes' => 'Base máxima de cotización anual contingencias comunes'],
            ['key' => 'ss.tope_min_base_anual',
                'value' => self::topeMinBaseAnual($year),
                'source_url' => self::ssOrden($year),
                'notes' => 'Base mínima de cotización anual contingencias comunes'],
            ['key' => 'ss.salario_minimo_interprofesional',
                'value' => self::smi($year),
                'source_url' => self::smiBoe($year),
                'notes' => 'SMI mensual (14 pagas)'],
            ['key' => 'ss.iprem_mensual',
                'value' => self::iprem($year),
                'source_url' => self::smiBoe($year),
                'notes' => 'IPREM mensual (12 pagas) — Ley PGE'],

            // Tipo MEI
            ['key' => 'ss.mei_total',
                'value' => self::meiTotal($year),
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2023-2954',
                'notes' => 'Mecanismo Equidad Intergeneracional total (RD-ley 13/2022 + RD-ley 8/2023)'],

            // Autónomos
            ['key' => 'autonomo.tipo_cotizacion_total', 'value' => 31.66,
                'source_url' => self::BOE_RDLEY_13_2022,
                'notes' => 'Tipo total cotización RETA (28.30 contingencias comunes + 1.30 ATEP + 0.90 cese + 0.10 FP + MEI)'],
            ['key' => 'autonomo.reduccion_inicio_actividad',
                'value' => ['cuota_reducida' => 80, 'meses' => 12],
                'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-12482',
                'notes' => 'Tarifa plana 80 €/mes los primeros 12 meses (RD-ley 13/2022 + Ley 6/2017 mod.)'],

            // IVA
            ['key' => 'iva.tipo_general', 'value' => 21.0,
                'source_url' => self::BOE_LEY_37_1992],
            ['key' => 'iva.tipo_reducido', 'value' => 10.0,
                'source_url' => self::BOE_LEY_37_1992],
            ['key' => 'iva.tipo_superreducido', 'value' => 4.0,
                'source_url' => self::BOE_LEY_37_1992],
            ['key' => 'iva.recargo_equivalencia',
                'value' => ['general' => 5.2, 'reducido' => 1.4, 'superreducido' => 0.5, 'tabaco' => 1.75],
                'source_url' => self::BOE_LEY_37_1992,
                'notes' => 'Recargo de equivalencia minoristas (Ley 37/1992 art. 161)'],

            // IS
            ['key' => 'is.tipo_general', 'value' => 25.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'notes' => 'Tipo general Impuesto sobre Sociedades (Ley 27/2014)'],
            ['key' => 'is.tipo_micro', 'value' => self::isMicro($year),
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'notes' => 'Tipo microempresas (cifra negocio < 1 M €)'],
            ['key' => 'is.tipo_startup', 'value' => 15.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-21739',
                'notes' => 'Empresas emergentes Ley 28/2022 — primeros 4 ejercicios con BI positiva'],
            ['key' => 'is.tipo_socimi', 'value' => 0.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-17000',
                'notes' => 'SOCIMI — 0 % en sociedad + 19 % retención dividendo socio (Ley 11/2009)'],
            ['key' => 'is.tipo_sicav', 'value' => 1.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'notes' => 'SICAV — 1 % tras reforma 2022'],
            ['key' => 'is.tipo_cooperativas_protegidas', 'value' => 20.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1990-12597',
                'notes' => 'Cooperativas protegidas — 20 % rdto. cooperativo, 25 % extracoop. (Ley 20/1990)'],
            ['key' => 'is.tipo_esfl', 'value' => 10.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2002-25039',
                'notes' => 'Entidades sin fines lucrativos Ley 49/2002 — 10 % sobre rentas no exentas'],
            ['key' => 'is.tipo_retencion', 'value' => 19.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'notes' => 'Tipo de retención general en IS'],
            ['key' => 'is.pago_fraccionado_minimo', 'value' => 23.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'notes' => 'Pago fraccionado mínimo grandes empresas (cifra > 10 M €) — 23 %'],

            // ITP / AJD / ISD (referencia general — varían por CCAA)
            ['key' => 'itp.tipo_general_estatal_supletorio', 'value' => 6.0,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'notes' => 'ITP transmisiones onerosas tipo general supletorio estatal (RDLeg 1/1993)'],
            ['key' => 'itp.ajd_documentos_notariales_supletorio', 'value' => 0.5,
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'notes' => 'AJD documentos notariales tipo supletorio'],

            // IIVTNU (Plusvalía municipal)
            ['key' => 'iivtnu.coeficientes_max',
                'value' => self::iivtnuCoeficientes($year),
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2021-19511',
                'notes' => 'Coeficientes máximos IIVTNU plusvalía municipal post-STC (RD-ley 26/2021)'],

            // Indemnizaciones / dietas exentas (RD 439/2007 art. 9)
            ['key' => 'irpf.dieta_manutencion_exenta_nacional', 'value' => 26.67,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Dieta manutención exenta nacional (sin pernoctar) RD 439/2007 art. 9'],
            ['key' => 'irpf.dieta_manutencion_exenta_extranjero', 'value' => 48.08,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Dieta manutención exenta extranjero (sin pernoctar)'],
            ['key' => 'irpf.dieta_pernocta_exenta_nacional', 'value' => 53.34,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Dieta manutención exenta nacional (con pernoctar)'],
            ['key' => 'irpf.dieta_pernocta_exenta_extranjero', 'value' => 91.35,
                'source_url' => self::BOE_RD_439_2007,
                'notes' => 'Dieta manutención exenta extranjero (con pernoctar)'],
            ['key' => 'irpf.kilometraje_exento', 'value' => 0.26,
                'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2023-15703',
                'notes' => 'Kilometraje exento IRPF (subido de 0.19 a 0.26 €/km en 2023)'],

            // Reducciones IRPF
            ['key' => 'irpf.reduccion_rendimientos_irregulares', 'value' => 30.0,
                'source_url' => $boeIrpf,
                'notes' => 'Reducción rentas irregulares (>2 años) art. 18 LIRPF, tope 300k'],
            ['key' => 'irpf.reduccion_rendimientos_irregulares_tope', 'value' => 300000,
                'source_url' => $boeIrpf,
                'notes' => 'Tope reducción rentas irregulares'],
            ['key' => 'irpf.gastos_genericos_eds_porcentaje', 'value' => 7.0,
                'source_url' => $boeIrpf,
                'notes' => 'Gastos genéricos EDS — Ley 11/2024 subió del 5 al 7 % desde 2023'],
            ['key' => 'irpf.gastos_genericos_eds_tope', 'value' => 2000.0,
                'source_url' => $boeIrpf,
                'notes' => 'Tope gastos genéricos EDS — 2.000 € anuales'],

            // Régimen Beckham
            ['key' => 'irpf.beckham_tipo_general', 'value' => 24.0,
                'source_url' => $boeIrpf,
                'notes' => 'Régimen impatriados — 24 % hasta 600 k base'],
            ['key' => 'irpf.beckham_tipo_top', 'value' => 47.0,
                'source_url' => $boeIrpf,
                'notes' => 'Régimen impatriados — 47 % por encima de 600 k base'],
            ['key' => 'irpf.beckham_tope', 'value' => 600000,
                'source_url' => $boeIrpf,
                'notes' => 'Régimen impatriados — umbral cambio de tipo'],

            // Mínimo exento declarar
            ['key' => 'irpf.minimo_exento_declarar_un_pagador',
                'value' => self::minimoExentoUnPagador($year),
                'source_url' => $boeIrpf,
                'notes' => 'Umbral obligación declarar IRPF — un solo pagador'],
            ['key' => 'irpf.minimo_exento_declarar_varios_pagadores',
                'value' => 15876,
                'source_url' => $boeIrpf,
                'notes' => 'Umbral obligación declarar IRPF — varios pagadores (≥ 1.500 € del 2.º+)'],
        ];
    }

    /**
     * Parámetros específicos por CCAA (deducciones autonómicas, mínimos personales propios…).
     *
     * @return list<array<string,mixed>>
     */
    public static function regionalParameters(int $year): array
    {
        $params = [];

        // Madrid — Ley 13/2022 + Ley 1/2024 (deflactación). Mantienen mínimos estatales pero
        // tienen deducciones autonómicas específicas (nacimiento, alquiler, etc.).
        $params[] = ['region_code' => 'MD', 'key' => 'irpf.deduccion_alquiler_vivienda_porcentaje', 'value' => 30.0,
            'source_url' => 'https://www.bocm.es/',
            'notes' => 'Madrid — deducción alquiler vivienda habitual menores de 35 años'];
        $params[] = ['region_code' => 'MD', 'key' => 'irpf.deduccion_alquiler_vivienda_tope', 'value' => 1000.0,
            'source_url' => 'https://www.bocm.es/',
            'notes' => 'Madrid — tope deducción alquiler'];
        $params[] = ['region_code' => 'MD', 'key' => 'irpf.deduccion_nacimiento_adopcion', 'value' => 600.0,
            'source_url' => 'https://www.bocm.es/',
            'notes' => 'Madrid — deducción nacimiento o adopción primer hijo'];
        $params[] = ['region_code' => 'MD', 'key' => 'irpf.deduccion_familia_numerosa', 'value' => 1200.0,
            'source_url' => 'https://www.bocm.es/',
            'notes' => 'Madrid — deducción familia numerosa de categoría general'];

        // Cataluña — Ley 5/2020 con modificaciones por Ley 12/2023.
        $params[] = ['region_code' => 'CT', 'key' => 'irpf.deduccion_alquiler_vivienda_porcentaje', 'value' => 10.0,
            'source_url' => 'https://dogc.gencat.cat/',
            'notes' => 'Cataluña — deducción alquiler vivienda habitual'];
        $params[] = ['region_code' => 'CT', 'key' => 'irpf.deduccion_alquiler_vivienda_tope', 'value' => 300.0,
            'source_url' => 'https://dogc.gencat.cat/',
            'notes' => 'Cataluña — tope deducción alquiler vivienda habitual general'];
        $params[] = ['region_code' => 'CT', 'key' => 'irpf.deduccion_nacimiento_adopcion', 'value' => 300.0,
            'source_url' => 'https://dogc.gencat.cat/',
            'notes' => 'Cataluña — deducción nacimiento o adopción'];
        $params[] = ['region_code' => 'CT', 'key' => 'irpf.minimo_personal_complementario', 'value' => 6105.0,
            'source_url' => 'https://dogc.gencat.cat/',
            'notes' => 'Cataluña — mínimo personal autonómico aumentado para rentas bajas (Ley 12/2023)'];

        // Andalucía — Ley 5/2021 + Ley 7/2022 (rebaja).
        $params[] = ['region_code' => 'AN', 'key' => 'irpf.deduccion_nacimiento_adopcion', 'value' => 200.0,
            'source_url' => 'https://www.juntadeandalucia.es/boja/',
            'notes' => 'Andalucía — deducción por nacimiento o adopción'];
        $params[] = ['region_code' => 'AN', 'key' => 'irpf.deduccion_familia_numerosa', 'value' => 200.0,
            'source_url' => 'https://www.juntadeandalucia.es/boja/',
            'notes' => 'Andalucía — deducción familia numerosa'];
        $params[] = ['region_code' => 'AN', 'key' => 'irpf.deduccion_alquiler_vivienda_porcentaje', 'value' => 15.0,
            'source_url' => 'https://www.juntadeandalucia.es/boja/',
            'notes' => 'Andalucía — deducción alquiler vivienda habitual menores de 35'];
        $params[] = ['region_code' => 'AN', 'key' => 'irpf.deduccion_alquiler_vivienda_tope', 'value' => 600.0,
            'source_url' => 'https://www.juntadeandalucia.es/boja/',
            'notes' => 'Andalucía — tope deducción alquiler'];

        // Comunidad Valenciana — Ley 9/2022 + Ley 7/2014 con actualizaciones.
        $params[] = ['region_code' => 'VC', 'key' => 'irpf.deduccion_alquiler_vivienda_porcentaje', 'value' => 20.0,
            'source_url' => 'https://dogv.gva.es/',
            'notes' => 'Comunidad Valenciana — deducción alquiler vivienda habitual'];
        $params[] = ['region_code' => 'VC', 'key' => 'irpf.deduccion_alquiler_vivienda_tope', 'value' => 800.0,
            'source_url' => 'https://dogv.gva.es/',
            'notes' => 'Comunidad Valenciana — tope deducción alquiler'];
        $params[] = ['region_code' => 'VC', 'key' => 'irpf.deduccion_nacimiento_adopcion', 'value' => 270.0,
            'source_url' => 'https://dogv.gva.es/',
            'notes' => 'Comunidad Valenciana — deducción por nacimiento o adopción'];
        $params[] = ['region_code' => 'VC', 'key' => 'irpf.deduccion_familia_numerosa', 'value' => 330.0,
            'source_url' => 'https://dogv.gva.es/',
            'notes' => 'Comunidad Valenciana — deducción familia numerosa'];

        // ITP por CCAA (tipos generales actualmente vigentes).
        $params[] = ['region_code' => 'MD', 'key' => 'itp.tipo_general_tpo', 'value' => 6.0,
            'source_url' => 'https://www.bocm.es/',
            'notes' => 'Madrid — ITP TPO tipo general'];
        $params[] = ['region_code' => 'CT', 'key' => 'itp.tipo_general_tpo', 'value' => 10.0,
            'source_url' => 'https://dogc.gencat.cat/',
            'notes' => 'Cataluña — ITP TPO tipo general'];
        $params[] = ['region_code' => 'AN', 'key' => 'itp.tipo_general_tpo', 'value' => 7.0,
            'source_url' => 'https://www.juntadeandalucia.es/boja/',
            'notes' => 'Andalucía — ITP TPO tipo único 7 %'];
        $params[] = ['region_code' => 'VC', 'key' => 'itp.tipo_general_tpo', 'value' => 10.0,
            'source_url' => 'https://dogv.gva.es/',
            'notes' => 'Comunidad Valenciana — ITP TPO tipo general'];

        return $params;
    }

    /**
     * Coeficientes máximos plusvalía municipal — RD-ley 26/2021 + actualizaciones anuales LPGE.
     *
     * @return array<string, float>
     */
    public static function iivtnuCoeficientes(int $year): array
    {
        // Tabla del art. 107.4 TRLHL tras STC + actualizaciones LPGE 2023 y 2024.
        return [
            'inferior_1_anio' => 0.15,
            '1_anio' => 0.15,
            '2_anios' => 0.14,
            '3_anios' => 0.14,
            '4_anios' => 0.16,
            '5_anios' => 0.18,
            '6_anios' => 0.19,
            '7_anios' => 0.18,
            '8_anios' => 0.15,
            '9_anios' => 0.12,
            '10_anios' => 0.10,
            '11_anios' => 0.09,
            '12_anios' => 0.09,
            '13_anios' => 0.09,
            '14_anios' => 0.09,
            '15_anios' => 0.10,
            '16_anios' => 0.13,
            '17_anios' => 0.17,
            '18_anios' => 0.23,
            '19_anios' => 0.29,
            '20_anios_o_mas' => 0.45,
        ];
    }

    public static function minimoExentoUnPagador(int $year): float
    {
        return match ($year) {
            2023 => 15000.0,
            2024 => 15876.0, // RD-ley 4/2024 lo subió de 15.000 a 15.876
            2025 => 15876.0,
            2026 => 15876.0,
            default => 15876.0,
        };
    }

    /**
     * Reducción por rendimientos del trabajo (Ley 35/2006 art. 20).
     * Hay umbrales y tramos. Devolvemos el dataset como JSON estructurado.
     *
     * @return array{minima: float, umbral_max: float, umbral_neto: float, formula: string}
     */
    public static function reduccionTrabajo(int $year): array
    {
        // Vigente desde 2023 tras Ley 31/2022. Vigente para 2024-2026 salvo cambio.
        return [
            'minima' => 6498.0,
            'umbral_max' => 19747.50,
            'umbral_neto' => 14852.0,
            'formula' => 'Si rendimiento neto previo ≤ 14.852 € → 6.498. Si ≤ 19.747,50 → 6.498 - 1,14 × (rendimiento - 14.852). Si > 19.747,50 → 0',
        ];
    }

    /**
     * Topes de cotización SS por año. Fuente: Orden anual de cotización + RD-ley.
     */
    public static function topeMaxBaseAnual(int $year): float
    {
        return match ($year) {
            2023 => 4495.50 * 12, // 53946 anual
            2024 => 4720.50 * 12, // 56646 anual
            2025 => 4909.50 * 12, // 58914 anual
            2026 => 5108.40 * 12, // estimación 4.05% PGE 2026
            default => 0.0,
        };
    }

    public static function topeMinBaseAnual(int $year): float
    {
        return match ($year) {
            2023 => 1166.70 * 12,
            2024 => 1323.00 * 12,
            2025 => 1381.20 * 12,
            2026 => 1437.00 * 12,
            default => 0.0,
        };
    }

    public static function smi(int $year): float
    {
        return match ($year) {
            2023 => 1080.00,
            2024 => 1134.00,
            2025 => 1184.00,
            2026 => 1230.00,
            default => 0.0,
        };
    }

    public static function smiBoe(int $year): string
    {
        return match ($year) {
            2023 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2023-2538',
            2024 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-2272',
            2025 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2025-2226',
            2026 => 'https://www.boe.es/buscar/',
            default => 'https://www.boe.es/',
        };
    }

    public static function iprem(int $year): float
    {
        return match ($year) {
            2023 => 600.00,
            2024 => 600.00,
            2025 => 600.00,
            2026 => 612.00,
            default => 0.0,
        };
    }

    public static function meiTotal(int $year): float
    {
        return match ($year) {
            2023 => 0.6,
            2024 => 0.7,
            2025 => 0.8,
            2026 => 0.9,
            default => 0.0,
        };
    }

    public static function meiEmpleador(int $year): float
    {
        return round(self::meiTotal($year) * 5 / 6, 4);
    }

    public static function meiTrabajador(int $year): float
    {
        return round(self::meiTotal($year) * 1 / 6, 4);
    }

    public static function isMicro(int $year): float
    {
        // Microempresas Ley 7/2024: en el primer tramo (BI ≤ 50k) 21 % en 2025, 19 % en 2026, 17 % desde 2027.
        // El tipo en BI > 50k baja también. Aquí almacenamos el primer tramo como representativo.
        return match ($year) {
            2023 => 23.0,
            2024 => 23.0,
            2025 => 21.0,
            2026 => 19.0,
            default => 25.0,
        };
    }

    public static function ssOrden(int $year): string
    {
        return match ($year) {
            2023 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2023-1383',
            2024 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-3458',
            2025 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2025-2226',
            2026 => 'https://www.seg-social.es/',
            default => 'https://www.seg-social.es/',
        };
    }

    /**
     * Tipos cotización SS para Régimen General + RETA.
     *
     * @return list<array<string,mixed>>
     */
    public static function socialSecurityRates(int $year): array
    {
        $orden = self::ssOrden($year);
        $baseMax = self::topeMaxBaseAnual($year) / 12;
        $baseMin = self::topeMinBaseAnual($year) / 12;

        return [
            // Régimen General
            [
                'regime' => 'RG',
                'contingency' => 'contingencias_comunes',
                'rate_employer' => 23.60,
                'rate_employee' => 4.70,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => $orden,
                'notes' => 'Tipo total 28.30 % (23.60 empresa + 4.70 trabajador)',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'desempleo_indefinido',
                'rate_employer' => 5.50,
                'rate_employee' => 1.55,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => $orden,
                'notes' => 'Contrato indefinido (incluido fijo-discontinuo)',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'desempleo_temporal',
                'rate_employer' => 6.70,
                'rate_employee' => 1.60,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => $orden,
                'notes' => 'Contrato temporal (cualquier modalidad)',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'fp',
                'rate_employer' => 0.60,
                'rate_employee' => 0.10,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => $orden,
                'notes' => 'Formación Profesional',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'fogasa',
                'rate_employer' => 0.20,
                'rate_employee' => 0.00,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => $orden,
                'notes' => 'FOGASA — solo a cargo de la empresa',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'mei',
                'rate_employer' => self::meiEmpleador($year),
                'rate_employee' => self::meiTrabajador($year),
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2023-2954',
                'notes' => 'Mecanismo Equidad Intergeneracional. Reparto 5/6 empresa, 1/6 trabajador.',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'atep',
                'rate_employer' => 1.50,
                'rate_employee' => 0.00,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2007-22390',
                'notes' => 'Accidentes trabajo y enfermedad profesional. Tipo variable según CNAE — 1.50 % representativo (oficinas).',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'horas_extra_estructurales',
                'rate_employer' => 23.60,
                'rate_employee' => 4.70,
                'base_min' => null,
                'base_max' => null,
                'source_url' => $orden,
                'notes' => 'Cotización adicional horas extraordinarias estructurales',
            ],
            [
                'regime' => 'RG',
                'contingency' => 'horas_extra_otras',
                'rate_employer' => 12.00,
                'rate_employee' => 2.00,
                'base_min' => null,
                'base_max' => null,
                'source_url' => $orden,
                'notes' => 'Cotización adicional horas extra distintas a las estructurales',
            ],

            // Régimen autónomos (RETA) — tipo único 31.66 % sobre base elegida
            [
                'regime' => 'RETA',
                'contingency' => 'contingencias_comunes',
                'rate_employer' => 0.00,
                'rate_employee' => 28.30,
                'base_min' => $baseMin, // referencia base mínima absoluta
                'base_max' => $baseMax,
                'source_url' => self::BOE_RDLEY_13_2022,
                'notes' => 'RETA contingencias comunes 28.30 % a cargo del autónomo',
            ],
            [
                'regime' => 'RETA',
                'contingency' => 'atep',
                'rate_employer' => 0.00,
                'rate_employee' => 1.30,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => self::BOE_RDLEY_13_2022,
                'notes' => 'RETA contingencias profesionales (cobertura obligatoria desde RD-ley 28/2018)',
            ],
            [
                'regime' => 'RETA',
                'contingency' => 'cese_actividad',
                'rate_employer' => 0.00,
                'rate_employee' => 0.90,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => self::BOE_RDLEY_13_2022,
                'notes' => 'RETA cese de actividad (paro autónomos)',
            ],
            [
                'regime' => 'RETA',
                'contingency' => 'fp',
                'rate_employer' => 0.00,
                'rate_employee' => 0.10,
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => self::BOE_RDLEY_13_2022,
                'notes' => 'RETA formación profesional',
            ],
            [
                'regime' => 'RETA',
                'contingency' => 'mei',
                'rate_employer' => 0.00,
                'rate_employee' => self::meiTotal($year),
                'base_min' => $baseMin,
                'base_max' => $baseMax,
                'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2023-2954',
                'notes' => 'RETA MEI — autónomo paga el total (no hay empleador)',
            ],
        ];
    }

    /**
     * 15 tramos de autónomos por año.
     * RD-ley 13/2022 fijó la senda 2023-2025; las tablas 2024 y 2025 se publicaron
     * en el BOE con prórrogas (RD-ley 8/2023 confirmó 2024). Para 2026 se mantiene
     * la última tabla publicada hasta nueva orden.
     *
     * @return list<array<string,mixed>>
     */
    public static function autonomoBrackets(int $year): array
    {
        $rows2023 = [
            // [bracket, from_yield mensual, to_yield, base_min, base_max, cuota_min, cuota_max]
            [1, 0, 670.00, 751.63, 849.66, 230, 260],
            [2, 670.01, 900.00, 849.67, 900.00, 260, 275],
            [3, 900.01, 1166.70, 898.69, 1166.70, 275, 357],
            [4, 1166.71, 1300.00, 950.98, 1300.00, 291, 398],
            [5, 1300.01, 1500.00, 960.78, 1500.00, 294, 460],
            [6, 1500.01, 1700.00, 960.78, 1700.00, 294, 521],
            [7, 1700.01, 1850.00, 1013.07, 1850.00, 310, 567],
            [8, 1850.01, 2030.00, 1029.41, 2030.00, 315, 622],
            [9, 2030.01, 2330.00, 1045.75, 2330.00, 320, 714],
            [10, 2330.01, 2760.00, 1078.43, 2760.00, 330, 846],
            [11, 2760.01, 3190.00, 1143.79, 3190.00, 350, 978],
            [12, 3190.01, 3620.00, 1209.15, 3620.00, 370, 1110],
            [13, 3620.01, 4050.00, 1274.51, 4050.00, 390, 1241],
            [14, 4050.01, 6000.00, 1372.55, 4495.50, 420, 1377],
            [15, 6000.01, null, 1633.99, 4495.50, 500, 1377],
        ];

        $rows2024 = [
            [1, 0, 670.00, 735.29, 816.99, 225, 250],
            [2, 670.01, 900.00, 816.99, 900.00, 250, 275],
            [3, 900.01, 1166.70, 872.55, 1166.70, 267, 357],
            [4, 1166.71, 1300.00, 950.98, 1300.00, 291, 398],
            [5, 1300.01, 1500.00, 960.78, 1500.00, 294, 460],
            [6, 1500.01, 1700.00, 960.78, 1700.00, 294, 521],
            [7, 1700.01, 1850.00, 1045.75, 1850.00, 320, 567],
            [8, 1850.01, 2030.00, 1062.09, 2030.00, 325, 622],
            [9, 2030.01, 2330.00, 1078.43, 2330.00, 330, 714],
            [10, 2330.01, 2760.00, 1111.11, 2760.00, 340, 846],
            [11, 2760.01, 3190.00, 1176.47, 3190.00, 360, 978],
            [12, 3190.01, 3620.00, 1241.83, 3620.00, 380, 1110],
            [13, 3620.01, 4050.00, 1307.19, 4050.00, 400, 1241],
            [14, 4050.01, 6000.00, 1454.25, 4720.50, 445, 1446],
            [15, 6000.01, null, 1732.03, 4720.50, 530, 1446],
        ];

        $rows2025 = [
            [1, 0, 670.00, 653.59, 718.95, 200, 220],
            [2, 670.01, 900.00, 718.95, 900.00, 220, 275],
            [3, 900.01, 1166.70, 849.67, 1166.70, 260, 357],
            [4, 1166.71, 1300.00, 950.98, 1300.00, 291, 398],
            [5, 1300.01, 1500.00, 960.78, 1500.00, 294, 460],
            [6, 1500.01, 1700.00, 960.78, 1700.00, 294, 521],
            [7, 1700.01, 1850.00, 1143.79, 1850.00, 350, 567],
            [8, 1850.01, 2030.00, 1209.15, 2030.00, 370, 622],
            [9, 2030.01, 2330.00, 1274.51, 2330.00, 390, 714],
            [10, 2330.01, 2760.00, 1356.21, 2760.00, 415, 846],
            [11, 2760.01, 3190.00, 1437.91, 3190.00, 440, 978],
            [12, 3190.01, 3620.00, 1519.61, 3620.00, 465, 1110],
            [13, 3620.01, 4050.00, 1601.31, 4050.00, 490, 1241],
            [14, 4050.01, 6000.00, 1732.03, 4909.50, 530, 1505],
            [15, 6000.01, null, 1928.10, 4909.50, 590, 1505],
        ];

        // Para 2026 prorrogamos las tablas 2025 hasta nueva orden BOE.
        $rows2026 = $rows2025;
        foreach ($rows2026 as &$row) {
            // Recalculamos base_max para 2026 con tope 2026 estimado.
            if ($row[0] >= 14) {
                $row[4] = 5108.40;
                $row[6] = 1567;
            }
        }
        unset($row);

        $sourceMap = [
            2023 => self::BOE_RDLEY_13_2022,
            2024 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-3458',
            2025 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2025-2226',
            2026 => 'https://www.seg-social.es/',
        ];

        $rowsByYear = [
            2023 => $rows2023,
            2024 => $rows2024,
            2025 => $rows2025,
            2026 => $rows2026,
        ];

        $rows = $rowsByYear[$year] ?? $rows2025;
        $sourceUrl = $sourceMap[$year] ?? self::BOE_RDLEY_13_2022;

        $output = [];
        foreach ($rows as $row) {
            $output[] = [
                'bracket_number' => $row[0],
                'from_yield' => $row[1],
                'to_yield' => $row[2],
                'base_min' => $row[3],
                'base_max' => $row[4],
                'monthly_quota_min' => $row[5],
                'monthly_quota_max' => $row[6],
                'source_url' => $sourceUrl,
            ];
        }

        return $output;
    }

    /**
     * Tipos IVA por sector/producto.
     *
     * Cubre los principales sectores y productos. Algunas reducciones son
     * temporales por RD-Leyes anti-inflación (BOE-A-2022-22128, BOE-A-2024-X).
     *
     * @return list<array<string,mixed>>
     */
    public static function vatRates(int $year): array
    {
        $boeIva = self::BOE_LEY_37_1992;
        $boeAntiInflation2022 = 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2022-22128';
        $boeAntiInflation2024 = 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2023-26452';

        // Helper para tipos transitorios alimentos básicos
        $panTipo = self::tipoTransitorioAlimentos($year, 'pan_basico');
        $aceiteTipo = self::tipoTransitorioAlimentos($year, 'aceite_oliva');
        $aceiteSemillasTipo = self::tipoTransitorioAlimentos($year, 'aceite_semillas');
        $pastasTipo = self::tipoTransitorioAlimentos($year, 'pastas');

        $rows = [
            // Tipo general
            ['keyword' => 'general', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Tipo general por defecto', 'source_url' => $boeIva],

            // Productos básicos (alimentos esenciales — RD-Ley anti-inflación)
            ['keyword' => 'pan_comun', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Pan común y harina panificable', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'leche_natural', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Leche natural, certificada, pasteurizada, concentrada o desnatada', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'queso', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Queso natural', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'huevos', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Huevos frescos', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'frutas', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Frutas frescas (no procesadas)', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'verduras', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Verduras y hortalizas frescas', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'legumbres', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Legumbres', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'tuberculos', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Tubérculos (patata, etc.)', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'cereales', 'rate_type' => self::rateTypeForRate($panTipo), 'rate' => $panTipo,
                'description' => 'Cereales', 'source_url' => $boeAntiInflation2022],

            // Aceites — tipo especial 5 % desde 2024 alguna parte del año
            ['keyword' => 'aceite_oliva', 'rate_type' => self::rateTypeForRate($aceiteTipo), 'rate' => $aceiteTipo,
                'description' => 'Aceite de oliva (RD-ley 4/2024 → tipo especial)', 'source_url' => $boeAntiInflation2024],
            ['keyword' => 'aceite_semillas', 'rate_type' => self::rateTypeForRate($aceiteSemillasTipo), 'rate' => $aceiteSemillasTipo,
                'description' => 'Aceite de semillas', 'source_url' => $boeAntiInflation2024],
            ['keyword' => 'pastas_alimenticias', 'rate_type' => self::rateTypeForRate($pastasTipo), 'rate' => $pastasTipo,
                'description' => 'Pastas alimenticias', 'source_url' => $boeAntiInflation2024],

            // Superreducido permanente
            ['keyword' => 'libros_papel', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Libros, periódicos y revistas en papel', 'source_url' => $boeIva],
            ['keyword' => 'libros_electronicos', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Libros y publicaciones digitales (Ley 11/2020)', 'source_url' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2020-17264'],
            ['keyword' => 'medicamentos_humanos', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Medicamentos para uso humano', 'source_url' => $boeIva],
            ['keyword' => 'protesis_implantes', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Prótesis, implantes y productos sanitarios para personas con discapacidad', 'source_url' => $boeIva],
            ['keyword' => 'vehiculos_movilidad_reducida', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Vehículos para personas con movilidad reducida', 'source_url' => $boeIva],
            ['keyword' => 'vivienda_proteccion_oficial', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Viviendas de protección oficial régimen especial', 'source_url' => $boeIva],

            // Reducido 10 %
            ['keyword' => 'viviendas_obra_nueva', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Entrega de viviendas obra nueva', 'source_url' => $boeIva],
            ['keyword' => 'transporte_viajeros', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Transporte de viajeros y equipajes', 'source_url' => $boeIva],
            ['keyword' => 'hosteleria', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Servicios de hostelería, restauración', 'source_url' => $boeIva],
            ['keyword' => 'agua', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Agua potable para consumo humano', 'source_url' => $boeIva],
            ['keyword' => 'alimentos_general', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Alimentos en general (no esenciales)', 'source_url' => $boeIva],
            ['keyword' => 'medicamentos_veterinarios', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Medicamentos veterinarios', 'source_url' => $boeIva],
            ['keyword' => 'productos_higiene_femenina', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Compresas, tampones y protegeslips (Ley 31/2022 → 4 %)', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'preservativos_anticonceptivos', 'rate_type' => 'super_reduced', 'rate' => 4.00,
                'description' => 'Preservativos y anticonceptivos no medicinales', 'source_url' => $boeAntiInflation2022],
            ['keyword' => 'gas_natural', 'rate_type' => 'reduced', 'rate' => self::tipoTransitorioEnergia($year, 'gas'),
                'description' => 'Gas natural (medida temporal RD-ley 8/2023)', 'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2023-26452'],
            ['keyword' => 'electricidad', 'rate_type' => self::rateTypeForRate(self::tipoTransitorioEnergia($year, 'electricidad')), 'rate' => self::tipoTransitorioEnergia($year, 'electricidad'),
                'description' => 'Electricidad (medida temporal anti-inflación)', 'source_url' => 'https://www.boe.es/diario_boe/txt.php?id=BOE-A-2023-26452'],
            ['keyword' => 'cine_teatro_conciertos', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Entradas a cine, teatro, conciertos', 'source_url' => $boeIva],
            ['keyword' => 'instalaciones_deportivas', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Servicios de instalaciones deportivas', 'source_url' => $boeIva],
            ['keyword' => 'flores_plantas', 'rate_type' => 'reduced', 'rate' => 10.00,
                'description' => 'Flores y plantas vivas ornamentales', 'source_url' => $boeIva],

            // Tipo general en sectores no listados arriba
            ['keyword' => 'ropa', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Textil y ropa', 'source_url' => $boeIva],
            ['keyword' => 'electronica', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Bienes de consumo electrónico', 'source_url' => $boeIva],
            ['keyword' => 'cosmeticos', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Cosméticos y productos de higiene general', 'source_url' => $boeIva],
            ['keyword' => 'mobiliario', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Mobiliario', 'source_url' => $boeIva],
            ['keyword' => 'automoviles', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Vehículos turismo', 'source_url' => $boeIva],
            ['keyword' => 'combustibles', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Carburantes (gasolina, diésel)', 'source_url' => $boeIva],
            ['keyword' => 'alcohol', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Bebidas alcohólicas', 'source_url' => $boeIva],
            ['keyword' => 'tabaco', 'rate_type' => 'general', 'rate' => 21.00,
                'description' => 'Tabaco', 'source_url' => $boeIva],

            // Exenciones
            ['keyword' => 'sanidad_publica', 'rate_type' => 'exempt', 'rate' => 0.00,
                'description' => 'Sanidad pública y privada (art. 20.1.2.º LIVA)', 'source_url' => $boeIva],
            ['keyword' => 'educacion_reglada', 'rate_type' => 'exempt', 'rate' => 0.00,
                'description' => 'Educación reglada (art. 20.1.9.º LIVA)', 'source_url' => $boeIva],
            ['keyword' => 'alquiler_vivienda', 'rate_type' => 'exempt', 'rate' => 0.00,
                'description' => 'Arrendamiento de vivienda habitual (art. 20.1.23.º LIVA)', 'source_url' => $boeIva],
            ['keyword' => 'servicios_financieros', 'rate_type' => 'exempt', 'rate' => 0.00,
                'description' => 'Servicios financieros y de seguros', 'source_url' => $boeIva],
        ];

        return $rows;
    }

    /**
     * Devuelve el tipo IVA temporal de alimentos básicos según el año + producto.
     * RD-Ley 20/2022 introdujo 0/5 % en 2023; RD-Ley 8/2023 prorrogó.
     * RD-Ley 4/2024 trajo el aceite de oliva al 5 %, luego 2 % final año.
     */
    public static function tipoTransitorioAlimentos(int $year, string $producto): float
    {
        // Pan, leche, queso, huevos, frutas, verduras, legumbres, tubérculos, cereales
        $tipoBasicoPorAnio = [
            2023 => 0.0,   // 0 % todo 2023
            2024 => 2.0,   // 0 % hasta junio, luego 2 %, después al 4 % (final año)
            2025 => 4.0,   // vuelta al superreducido permanente
            2026 => 4.0,
        ];

        // Aceite de oliva
        if ($producto === 'aceite_oliva') {
            return match ($year) {
                2023 => 5.0,   // RD-ley 20/2022 — 5 %
                2024 => 2.0,   // de 5 a 2 % en 2024 (RD-ley 4/2024)
                2025, 2026 => 4.0,
                default => 10.0,
            };
        }

        if ($producto === 'aceite_semillas') {
            return match ($year) {
                2023 => 5.0,
                2024 => 7.5,
                2025, 2026 => 10.0,
                default => 10.0,
            };
        }

        if ($producto === 'pastas') {
            return match ($year) {
                2023 => 5.0,
                2024 => 7.5,
                2025, 2026 => 10.0,
                default => 10.0,
            };
        }

        return $tipoBasicoPorAnio[$year] ?? 4.0;
    }

    /**
     * Tipo IVA gas/electricidad. Reducciones temporales 2022-2024.
     */
    public static function tipoTransitorioEnergia(int $year, string $tipo): float
    {
        if ($tipo === 'electricidad') {
            return match ($year) {
                2023 => 5.0,   // 5 % desde 1-jul-2022
                2024 => 10.0,  // subida a 10 % en 2024
                2025, 2026 => 21.0,
                default => 21.0,
            };
        }

        // Gas natural
        return match ($year) {
            2023 => 5.0,
            2024, 2025, 2026 => 21.0,
            default => 21.0,
        };
    }

    public static function rateTypeForRate(float $rate): string
    {
        return match (true) {
            $rate <= 0.0 => 'zero',
            $rate < 4.0 => 'special',
            $rate <= 4.0 => 'super_reduced',
            $rate <= 5.0 => 'special',
            $rate <= 10.0 => 'reduced',
            $rate <= 21.0 => 'general',
            default => 'general',
        };
    }
}
