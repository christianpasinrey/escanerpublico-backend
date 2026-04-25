<?php

namespace Database\Seeders\Modules\Tax\IncomeTax;

/**
 * Datos de módulos (signos/índices) por epígrafe IAE para Estimación Objetiva.
 *
 * Cobertura MVP: top-20 actividades EO según la Orden HFP/1359/2023 (BOE-A-2023-25876)
 * para 2024 y la Orden HFP/1397/2024 (BOE-A-2024-26896) para 2025. Incluye los
 * epígrafes IAE más frecuentes:
 *
 * Comercio minorista
 *   653.2 — Comercio menor material y aparatos eléctricos
 *   659.4 — Comercio menor libros, periódicos, revistas
 *   662.2 — Comercio menor toda clase artículos (otros bazares)
 *
 * Restauración
 *   671.4 — Restaurantes 2 tenedores
 *   671.5 — Restaurantes 1 tenedor
 *   672.1, 672.2, 672.3 — Cafeterías
 *   673.1 — Cafés y bares de categoría especial
 *   673.2 — Otros cafés y bares (los más comunes)
 *   675 — Quioscos situados en la vía pública
 *
 * Transporte
 *   721.1 — Transporte urbano colectivo
 *   721.2 — Transporte por autotaxi
 *   722 — Transporte mercancías por carretera
 *   757 — Servicios de mudanzas
 *
 * Servicios personales
 *   972.1 — Peluquería de señoras y caballeros
 *   972.2 — Salones e institutos de belleza
 *
 * Otros
 *   971.1 — Tinte, limpieza en seco, lavado y planchado
 *   973.3 — Servicios de copias de documentos
 *
 * Estructura del provider:
 *   - 'modules': cada signo/índice con su unidad y valor (€/unidad/año)
 *   - 'note': descripción legal y referencias
 *
 * Los valores se calcularon a partir de los anexos de la Orden HFP anual.
 * Cada año recalcular contra Orden vigente.
 *
 * @see https://www.boe.es/buscar/doc.php?id=BOE-A-2023-25876 (Orden HFP/1359/2023, módulos 2024)
 * @see https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26896 (Orden HFP/1397/2024, módulos 2025)
 */
class EoModulesDataProvider
{
    /**
     * @return array<string, array{
     *   description: string,
     *   modules: array<string, array{unit: string, value_per_unit: float, label: string}>,
     *   minimum_yield_index: float,
     *   source_url: string,
     *   notes: string
     * }>
     */
    public static function activities(int $year): array
    {
        // 2024 y 2025 mantienen estructura idéntica con valores casi iguales tras
        // la rebaja del 5 % del rendimiento neto (DT 32ª LIRPF) prorrogada en 2024.
        $sourceUrl = match (true) {
            $year >= 2025 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2024-26896',
            $year === 2024 => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2023-25876',
            default => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2022-22340',
        };

        return [
            // === RESTAURACIÓN ===
            '671.4' => [
                'description' => 'Restaurantes de 2 tenedores',
                'modules' => [
                    'personal_asalariado' => [
                        'unit' => 'persona/año',
                        'value_per_unit' => 11960.99,
                        'label' => 'Personal asalariado',
                    ],
                    'personal_no_asalariado' => [
                        'unit' => 'persona/año',
                        'value_per_unit' => 22489.46,
                        'label' => 'Personal no asalariado (titular)',
                    ],
                    'kw_potencia' => [
                        'unit' => 'kW/año',
                        'value_per_unit' => 305.94,
                        'label' => 'Potencia eléctrica (kW)',
                    ],
                    'mesas' => [
                        'unit' => 'mesa/año',
                        'value_per_unit' => 750.93,
                        'label' => 'Mesas',
                    ],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 671.4 - Restaurantes 2 tenedores. Anexo II Orden HFP.',
            ],
            '671.5' => [
                'description' => 'Restaurantes de 1 tenedor',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 6537.92, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 16753.49, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 196.31, 'label' => 'Potencia eléctrica (kW)'],
                    'mesas' => ['unit' => 'mesa/año', 'value_per_unit' => 384.45, 'label' => 'Mesas'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 671.5 - Restaurantes 1 tenedor. Anexo II Orden HFP.',
            ],
            '672.1' => [
                'description' => 'Cafeterías de tres tazas',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 1937.10, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 17085.55, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 537.49, 'label' => 'Potencia eléctrica (kW)'],
                    'mesas' => ['unit' => 'mesa/año', 'value_per_unit' => 332.94, 'label' => 'Mesas'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 672.1 - Cafeterías 3 tazas.',
            ],
            '672.2' => [
                'description' => 'Cafeterías de dos tazas',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 1659.75, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 14064.80, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 393.66, 'label' => 'Potencia eléctrica (kW)'],
                    'mesas' => ['unit' => 'mesa/año', 'value_per_unit' => 244.83, 'label' => 'Mesas'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 672.2 - Cafeterías 2 tazas.',
            ],
            '673.1' => [
                'description' => 'Cafés y bares de categoría especial',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 2027.32, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 14065.38, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 478.55, 'label' => 'Potencia eléctrica (kW)'],
                    'mesas' => ['unit' => 'mesa/año', 'value_per_unit' => 213.00, 'label' => 'Mesas'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 673.1 - Cafés y bares categoría especial.',
            ],
            '673.2' => [
                'description' => 'Otros cafés y bares (categoría general)',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 1136.08, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 9551.05, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 173.65, 'label' => 'Potencia eléctrica (kW)'],
                    'mesas' => ['unit' => 'mesa/año', 'value_per_unit' => 91.96, 'label' => 'Mesas'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 673.2 - Bares (los más numerosos).',
            ],
            '675' => [
                'description' => 'Quioscos situados en la vía pública',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 1593.26, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 11030.76, 'label' => 'Personal no asalariado (titular)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 138.77, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 675 - Quioscos vía pública.',
            ],

            // === TRANSPORTE ===
            '721.1' => [
                'description' => 'Transporte urbano colectivo',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 1098.81, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 10437.58, 'label' => 'Personal no asalariado (titular)'],
                    'plazas_vehiculo' => ['unit' => 'plaza/año', 'value_per_unit' => 130.41, 'label' => 'Plazas del vehículo'],
                ],
                'minimum_yield_index' => 0.13,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 721.1 - Transporte urbano colectivo.',
            ],
            '721.2' => [
                'description' => 'Transporte por autotaxi',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 980.84, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 4076.60, 'label' => 'Personal no asalariado (titular)'],
                    'distancia_km' => ['unit' => '1.000 km/año', 'value_per_unit' => 24.06, 'label' => 'Distancia recorrida (cada 1.000 km)'],
                ],
                'minimum_yield_index' => 0.13,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 721.2 - Taxi (módulo más usado en EO).',
            ],
            '722' => [
                'description' => 'Transporte de mercancías por carretera',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 3373.98, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 12453.74, 'label' => 'Personal no asalariado (titular)'],
                    'carga_vehiculos' => ['unit' => 'tm carga/año', 'value_per_unit' => 90.04, 'label' => 'Carga vehículos (toneladas)'],
                ],
                'minimum_yield_index' => 0.13,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 722 - Transporte mercancías carretera.',
            ],
            '757' => [
                'description' => 'Servicios de mudanzas',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 3450.65, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 13062.65, 'label' => 'Personal no asalariado (titular)'],
                    'carga_vehiculos' => ['unit' => 'tm carga/año', 'value_per_unit' => 91.43, 'label' => 'Carga vehículos (toneladas)'],
                ],
                'minimum_yield_index' => 0.13,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 757 - Mudanzas.',
            ],

            // === COMERCIO MINORISTA ===
            '653.2' => [
                'description' => 'Comercio menor material y aparatos eléctricos',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 4316.78, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 17013.91, 'label' => 'Personal no asalariado (titular)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 39.07, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 653.2 - Tiendas eléctricos.',
            ],
            '659.4' => [
                'description' => 'Comercio menor libros, periódicos, revistas',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 2926.09, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 13810.76, 'label' => 'Personal no asalariado (titular)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 39.07, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 659.4 - Librerías, kioscos.',
            ],
            '662.2' => [
                'description' => 'Comercio menor toda clase artículos (bazares)',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 2926.09, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 11630.39, 'label' => 'Personal no asalariado (titular)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 19.97, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 662.2 - Bazares.',
            ],

            // === SERVICIOS PERSONALES ===
            '972.1' => [
                'description' => 'Peluquería de señoras y caballeros',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 3110.42, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 9706.83, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 117.63, 'label' => 'Potencia eléctrica (kW)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 38.57, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 972.1 - Peluquerías.',
            ],
            '972.2' => [
                'description' => 'Salones e institutos de belleza',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 3486.14, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 13321.34, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 145.62, 'label' => 'Potencia eléctrica (kW)'],
                    'm2_local' => ['unit' => 'm²/año', 'value_per_unit' => 38.57, 'label' => 'Superficie local (m²)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 972.2 - Estética y belleza.',
            ],
            '971.1' => [
                'description' => 'Tinte, limpieza en seco, lavado y planchado',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 4214.04, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 16403.46, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 145.61, 'label' => 'Potencia eléctrica (kW)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 971.1 - Tintorerías.',
            ],
            '973.3' => [
                'description' => 'Servicios de copia de documentos con máquinas fotocopiadoras',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 4314.46, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 14820.13, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 218.42, 'label' => 'Potencia eléctrica (kW)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 973.3 - Copisterías.',
            ],
            '691.1' => [
                'description' => 'Reparación artículos eléctricos del hogar',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 3354.06, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 13321.34, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 117.96, 'label' => 'Potencia eléctrica (kW)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 691.1 - Reparación de electrodomésticos.',
            ],
            '691.2' => [
                'description' => 'Reparación de vehículos automóviles, bicicletas y otros',
                'modules' => [
                    'personal_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 4225.13, 'label' => 'Personal asalariado'],
                    'personal_no_asalariado' => ['unit' => 'persona/año', 'value_per_unit' => 14987.24, 'label' => 'Personal no asalariado (titular)'],
                    'kw_potencia' => ['unit' => 'kW/año', 'value_per_unit' => 161.75, 'label' => 'Potencia eléctrica (kW)'],
                ],
                'minimum_yield_index' => 0.10,
                'source_url' => $sourceUrl,
                'notes' => 'IAE 691.2 - Talleres mecánicos.',
            ],
        ];
    }

    /**
     * Lista compacta de epígrafes IAE soportados en EO MVP.
     *
     * @return list<string>
     */
    public static function supportedActivityCodes(int $year): array
    {
        return array_keys(self::activities($year));
    }

    /**
     * Devuelve la configuración de una actividad concreta para un año, o null si
     * no está cubierta en MVP.
     *
     * @return array{
     *   description: string,
     *   modules: array<string, array{unit: string, value_per_unit: float, label: string}>,
     *   minimum_yield_index: float,
     *   source_url: string,
     *   notes: string
     * }|null
     */
    public static function activity(int $year, string $code): ?array
    {
        return self::activities($year)[$code] ?? null;
    }
}
