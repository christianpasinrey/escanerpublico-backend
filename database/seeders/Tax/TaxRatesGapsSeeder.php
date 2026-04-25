<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;

/**
 * Rellena los `tax_rates` que TaxRatesSeeder dejó vacíos por complejidad
 * de las escalas multi-tier autonómicas (juego, hidrocarburos, ASJ) y
 * tributos con base imponible no monetaria (IIEE_ALCOHOL).
 *
 * Para escalas reales que no caben en un único `(rate, fixed_amount)` se
 * usa la columna `conditions` (JSON) con la descripción detallada de la
 * escala — la API expone esto literalmente para que un consumidor de open
 * data pueda reconstruir la fórmula completa.
 */
class TaxRatesGapsSeeder extends Seeder
{
    use WithoutModelEvents;

    private const ASJ_LEY = 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-9523';

    private const HIDROCARB_LEY = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741';

    private const JUEGO_LEY = 'https://www.boe.es/buscar/act.php?id=BOE-A-1977-25478';

    private const IIEE_ALCOHOL_LEY = 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741';

    private const LIRPF = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764';

    public function run(): void
    {
        $this->seedJuegoOnline();
        $this->seedHidrocarburosAutonomico();
        $this->seedTributosJuego();
        $this->seedIieeAlcohol();
        $this->seedIrpfReference();
    }

    /**
     * TASA_ASJ — Tasa fiscal sobre apuestas y juego online (Ley 13/2011 art. 48).
     * Los tipos son estatales pero la recaudación se cede a la CCAA donde
     * reside cada usuario. Por simplicidad publicamos los tipos estatales en
     * cada CCAA top-4 con conditions detallando la escala por modalidad.
     */
    private function seedJuegoOnline(): void
    {
        $modalidades = [
            ['modalidad' => 'apuestas_mutuas', 'rate' => 22.00, 'base' => 'rendimiento_neto'],
            ['modalidad' => 'apuestas_cruzadas', 'rate' => 25.00, 'base' => 'rendimiento_neto'],
            ['modalidad' => 'apuestas_contrapartida', 'rate' => 25.00, 'base' => 'rendimiento_neto'],
            ['modalidad' => 'concursos', 'rate' => 20.00, 'base' => 'ingresos_brutos'],
            ['modalidad' => 'otros_juegos', 'rate' => 25.00, 'base' => 'rendimiento_neto'],
            ['modalidad' => 'combinaciones_aleatorias', 'rate' => 10.00, 'base' => 'valor_premios'],
        ];

        foreach (['MD', 'CT', 'AN', 'VC'] as $region) {
            foreach ([2025, 2026] as $year) {
                $this->upsertWithConditions('TASA_ASJ', 'regional', $region, [
                    'year' => $year,
                    'rate' => 25.0000,
                    'source_url' => self::ASJ_LEY,
                    'conditions' => [
                        'note' => 'Tipo principal 25 %; modalidades específicas según Ley 13/2011 art. 48.7',
                        'modalidades' => $modalidades,
                    ],
                ]);
            }
        }
    }

    /**
     * TASA_HIDROCARBUROS_MINORISTA — Tipo autonómico Impuesto Hidrocarburos
     * (Ley 38/1992 art. 50 ter). Los tipos están integrados en el modelo 581.
     * Tras Ley 2/2012 muchas CCAA fijaron tramo autonómico residual o cero.
     */
    private function seedHidrocarburosAutonomico(): void
    {
        // €/1000 litros sobre productos de uso general. Valores aproximados
        // 2024-2025 según leyes anuales de cada CCAA. Para 2026 se asume
        // continuidad mientras no haya nueva Ley.
        $tipos = [
            'MD' => ['gasolinas' => 0.0, 'gasoleos_uso_general' => 0.0],     // Madrid suprimido
            'CT' => ['gasolinas' => 24.0, 'gasoleos_uso_general' => 24.0],   // Cataluña — tipo general
            'AN' => ['gasolinas' => 0.0, 'gasoleos_uso_general' => 0.0],     // Andalucía suprimido (Ley 4/2010 derogada 2018)
            'VC' => ['gasolinas' => 24.0, 'gasoleos_uso_general' => 24.0],   // Comunidad Valenciana
        ];

        foreach ($tipos as $region => $valores) {
            foreach ([2025, 2026] as $year) {
                $this->upsertWithConditions('TASA_HIDROCARBUROS_MINORISTA', 'regional', $region, [
                    'year' => $year,
                    'rate' => null,
                    'source_url' => self::HIDROCARB_LEY,
                    'conditions' => [
                        'unit' => 'EUR/1000_litros',
                        'tipos_por_producto' => $valores,
                        'note' => 'Tipo autonómico variable; se suma al estatal. Liquidación vía modelo 581.',
                    ],
                ]);
            }
        }
    }

    /**
     * TRIB_JUEGO — Tributos sobre el juego (RD-Ley 16/1977, cedidos a CCAA).
     * Cada CCAA fija escalas por modalidad (bingo, ruleta, máquinas tragaperras
     * tipo B/C, apuestas presenciales, casinos). Modelamos los tipos generales
     * y dejamos las escalas detalladas en conditions.
     */
    private function seedTributosJuego(): void
    {
        $escalasPorRegion = [
            'MD' => [
                'bingo' => 25.00, 'ruleta_otros' => 20.00, 'casinos' => 22.00,
                'maquinas_tipo_b_anual_eur' => 3500, 'maquinas_tipo_c_anual_eur' => 5000,
            ],
            'CT' => [
                'bingo' => 25.00, 'ruleta_otros' => 22.00, 'casinos' => 25.00,
                'maquinas_tipo_b_anual_eur' => 3500, 'maquinas_tipo_c_anual_eur' => 5000,
            ],
            'AN' => [
                'bingo' => 22.00, 'ruleta_otros' => 20.00, 'casinos' => 25.00,
                'maquinas_tipo_b_anual_eur' => 3300, 'maquinas_tipo_c_anual_eur' => 4900,
            ],
            'VC' => [
                'bingo' => 25.00, 'ruleta_otros' => 22.00, 'casinos' => 22.00,
                'maquinas_tipo_b_anual_eur' => 3500, 'maquinas_tipo_c_anual_eur' => 5000,
            ],
        ];

        foreach ($escalasPorRegion as $region => $escala) {
            foreach ([2025, 2026] as $year) {
                $this->upsertWithConditions('TRIB_JUEGO', 'regional', $region, [
                    'year' => $year,
                    'rate' => 25.0000,    // tipo testigo (bingo / casinos)
                    'source_url' => self::JUEGO_LEY,
                    'conditions' => [
                        'note' => 'Escala variable por modalidad de juego. Tipo principal 25 % bingo/casinos. Cuotas fijas anuales para máquinas tipo B/C.',
                        'modalidades' => $escala,
                    ],
                ]);
            }
        }
    }

    /**
     * IIEE_ALCOHOL — Impuestos Especiales sobre Alcohol y Bebidas Alcohólicas
     * (Ley 38/1992). Tipos por producto: cerveza, vino, derivadas, productos
     * intermedios. Base imponible es volumen (hl) o grados alcohólicos.
     */
    private function seedIieeAlcohol(): void
    {
        foreach ([2025, 2026] as $year) {
            $this->upsertWithConditions('IIEE_ALCOHOL', 'state', null, [
                'year' => $year,
                'rate' => null,
                'source_url' => self::IIEE_ALCOHOL_LEY,
                'conditions' => [
                    'note' => 'Tipos específicos por producto y graduación; base imponible no monetaria',
                    'tipos' => [
                        'cerveza_eur_hl_grado' => 2.75,
                        'vino_tranquilo_eur_hl' => 0,           // exento desde 1985
                        'productos_intermedios_eur_hl' => 64.13,
                        'bebidas_derivadas_eur_hl_alcohol_absoluto' => 1180.81,
                    ],
                ],
            ]);
        }
    }

    /**
     * IRPF — la escala progresiva está en `tax_brackets`. Aquí publicamos una
     * fila marcadora que apunta al dataset correcto.
     */
    private function seedIrpfReference(): void
    {
        foreach ([2025, 2026] as $year) {
            $this->upsertWithConditions('IRPF', 'state', null, [
                'year' => $year,
                'rate' => null,
                'source_url' => self::LIRPF,
                'conditions' => [
                    'note' => 'IRPF se modela como escala progresiva — consulta /api/v1/tax/brackets?filter[year]='.$year.'&filter[type]=irpf_general',
                    'applies_via' => 'tax_brackets',
                    'bracket_types' => ['irpf_general', 'irpf_ahorro'],
                    'scopes' => ['state', 'regional'],
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertWithConditions(string $code, string $scope, ?string $regionCode, array $row): void
    {
        $type = TaxType::query()
            ->where('code', $code)
            ->where('scope', $scope)
            ->when($regionCode === null, fn ($q) => $q->whereNull('region_code'))
            ->when($regionCode !== null, fn ($q) => $q->where('region_code', $regionCode))
            ->first();

        if ($type === null) {
            return;
        }

        $row['tax_type_id'] = $type->id;
        $row['region_code'] = $row['region_code'] ?? $regionCode;
        $row['valid_from'] = $row['valid_from'] ?? sprintf('%d-01-01', (int) $row['year']);

        TaxRate::updateOrCreate(
            [
                'tax_type_id' => $type->id,
                'year' => $row['year'],
                'region_code' => $row['region_code'],
            ],
            $row
        );
    }
}
