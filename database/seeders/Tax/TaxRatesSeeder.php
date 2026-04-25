<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;

/**
 * Tipos y cuantías vigentes para 2025 y 2026 de los `tax_types` cargados
 * por los seeders de catálogo (estatales, regionales y tasas).
 *
 * Convenciones:
 *  - `rate` se expresa en porcentaje (21.0000 = 21 %).
 *  - Para cuantías fijas (TASA_DNI, TASA_PASAPORTE…) se usa `fixed_amount`
 *    en € y `rate` queda NULL.
 *  - Tributos cuya escala es progresiva (IRPF) se modelan en M3 vía
 *    `tax_brackets`; aquí solo se incluyen los tipos generales o testigo.
 *  - Cuando un tipo es estable entre 2025 y 2026 se duplica la fila para
 *    permitir filtros por año sin lógica adicional en cliente.
 */
class TaxRatesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedStateImpuestos();
        $this->seedRegionalImpuestos();
        $this->seedStateTasas();
    }

    private function seedStateImpuestos(): void
    {
        // IS — tipo general 25 % (Ley 27/2014 art. 29).
        $this->upsert('IS', 'state', null, [
            ['year' => 2025, 'rate' => 25.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328'],
            ['year' => 2026, 'rate' => 25.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328'],
        ]);

        // IS — tipo reducido empresas reducida dimensión / nuevas (15 % en 2025, 17 % microempresa según facturación, etc.).
        $this->upsert('IS', 'state', null, [
            [
                'year' => 2025,
                'rate' => 15.0000,
                'conditions' => ['regimen' => 'IS_STARTUP', 'detalle' => 'Entidades de nueva creación, primer ejercicio con base imponible positiva y siguiente'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-22684',
            ],
            [
                'year' => 2026,
                'rate' => 15.0000,
                'conditions' => ['regimen' => 'IS_STARTUP', 'detalle' => 'Entidades de nueva creación'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-22684',
            ],
        ]);

        // IVA — General 21 %.
        $this->upsert('IVA', 'state', null, [
            ['year' => 2025, 'rate' => 21.0000, 'conditions' => ['categoria' => 'general'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
            ['year' => 2026, 'rate' => 21.0000, 'conditions' => ['categoria' => 'general'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
        ]);

        // IVA — Reducido 10 %.
        $this->upsert('IVA', 'state', null, [
            ['year' => 2025, 'rate' => 10.0000, 'conditions' => ['categoria' => 'reducido'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
            ['year' => 2026, 'rate' => 10.0000, 'conditions' => ['categoria' => 'reducido'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
        ]);

        // IVA — Superreducido 4 %.
        $this->upsert('IVA', 'state', null, [
            ['year' => 2025, 'rate' => 4.0000, 'conditions' => ['categoria' => 'superreducido'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
            ['year' => 2026, 'rate' => 4.0000, 'conditions' => ['categoria' => 'superreducido'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740'],
        ]);

        // IVA — Tipo especial 5 % alimentos básicos (RDL 4/2024 prorrogado) 2025; finalizado para 2026 (vuelve al 4/10).
        $this->upsert('IVA', 'state', null, [
            [
                'year' => 2025,
                'rate' => 2.0000,
                'conditions' => ['categoria' => 'especial_alimentos', 'vigencia' => 'enero-marzo 2025 sobre aceites de oliva y semillas, leche, huevos, pan, harinas, queso, frutas, verduras y legumbres'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2024-26780',
                'valid_from' => '2025-01-01',
                'valid_to' => '2025-03-31',
            ],
        ]);

        // ITP estatal — tipo general 6 % (cuando no hay regulación autonómica).
        $this->upsert('ITP', 'state', null, [
            ['year' => 2025, 'rate' => 6.0000, 'conditions' => ['supuesto' => 'inmuebles defecto estatal'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
            ['year' => 2026, 'rate' => 6.0000, 'conditions' => ['supuesto' => 'inmuebles defecto estatal'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
        ]);

        // AJD estatal — tipo gradual general 0,5 % (defecto sin regulación CCAA).
        $this->upsert('AJD', 'state', null, [
            ['year' => 2025, 'rate' => 0.5000, 'conditions' => ['supuesto' => 'cuota gradual notarial defecto estatal'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
            ['year' => 2026, 'rate' => 0.5000, 'conditions' => ['supuesto' => 'cuota gradual notarial defecto estatal'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
        ]);

        // ISD estatal — escala progresiva (testigo del primer tramo y del más alto).
        $this->upsert('ISD', 'state', null, [
            [
                'year' => 2025,
                'rate' => 7.6500,
                'base_min' => 0,
                'base_max' => 7993.46,
                'conditions' => ['descripcion' => 'primer tramo escala estatal Grupo II'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
            ],
            [
                'year' => 2025,
                'rate' => 34.0000,
                'base_min' => 797555.08,
                'conditions' => ['descripcion' => 'tramo más alto escala estatal'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
            ],
        ]);

        // IIEE Hidrocarburos — tipo general gasolinas (€/1000 l).
        $this->upsert('IIEE_HIDROCARBUROS', 'state', null, [
            [
                'year' => 2025,
                'rate' => null,
                'fixed_amount' => 472.69,
                'conditions' => ['producto' => 'gasolinas con plomo y gasolinas sin plomo de 98 IO o superior', 'unidad' => '€/1000 l'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
            ],
            [
                'year' => 2026,
                'rate' => null,
                'fixed_amount' => 472.69,
                'conditions' => ['producto' => 'gasolinas con plomo y gasolinas sin plomo de 98 IO o superior', 'unidad' => '€/1000 l'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
            ],
        ]);

        // IIEE Tabaco — tipo proporcional cigarrillos 51 %.
        $this->upsert('IIEE_TABACO', 'state', null, [
            ['year' => 2025, 'rate' => 51.0000, 'conditions' => ['producto' => 'cigarrillos', 'detalle' => 'tipo proporcional sobre PVP'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741'],
            ['year' => 2026, 'rate' => 51.0000, 'conditions' => ['producto' => 'cigarrillos', 'detalle' => 'tipo proporcional sobre PVP'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741'],
        ]);

        // IIEE Electricidad 5,11269632 %.
        $this->upsert('IIEE_ELECTRICIDAD', 'state', null, [
            ['year' => 2025, 'rate' => 5.1127, 'conditions' => ['detalle' => 'tipo general (5,11269632 %) restablecido tras suspensión transitoria'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741'],
            ['year' => 2026, 'rate' => 5.1127, 'conditions' => ['detalle' => 'tipo general'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741'],
        ]);

        // Primas de seguros 8 %.
        $this->upsert('IIEE_PRIMAS_SEGUROS', 'state', null, [
            ['year' => 2025, 'rate' => 8.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1996-29117'],
            ['year' => 2026, 'rate' => 8.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1996-29117'],
        ]);

        // IGIC — general 7 % y reducido 3 %.
        $this->upsert('IGIC', 'state', null, [
            ['year' => 2025, 'rate' => 7.0000, 'conditions' => ['categoria' => 'general'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-15021'],
            ['year' => 2025, 'rate' => 3.0000, 'conditions' => ['categoria' => 'reducido'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-15021'],
            ['year' => 2026, 'rate' => 7.0000, 'conditions' => ['categoria' => 'general'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-15021'],
        ]);

        // IPSI Ceuta/Melilla — tipo general aproximado 0,5%–10%, ponemos un valor testigo medio 4 %.
        $this->upsert('IPSI', 'state', null, [
            ['year' => 2025, 'rate' => 4.0000, 'conditions' => ['detalle' => 'tipo orientativo medio; depende de la ciudad y categoría'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-26233'],
            ['year' => 2026, 'rate' => 4.0000, 'conditions' => ['detalle' => 'tipo orientativo medio'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-26233'],
        ]);

        // Impuesto plásticos no reutilizables — 0,45 €/kg de plástico no reciclado.
        $this->upsert('IMPUESTO_PLASTICOS', 'state', null, [
            ['year' => 2025, 'rate' => null, 'fixed_amount' => 0.45, 'conditions' => ['unidad' => '€/kg plástico no reciclado'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-5809'],
            ['year' => 2026, 'rate' => null, 'fixed_amount' => 0.45, 'conditions' => ['unidad' => '€/kg plástico no reciclado'], 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-5809'],
        ]);

        // Impuesto sobre Depósitos Bancos — 0,03 %.
        $this->upsert('IMPUESTO_DEPOSITO_BANCOS', 'state', null, [
            ['year' => 2025, 'rate' => 0.0300, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15212'],
            ['year' => 2026, 'rate' => 0.0300, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15212'],
        ]);

        // Gravamen Banca (Ley 38/2022) — 4,8 % sobre margen + comisiones.
        $this->upsert('IMPUESTO_BONIF_BANCOS', 'state', null, [
            [
                'year' => 2025,
                'rate' => 4.8000,
                'conditions' => ['vigencia' => 'gravamen temporal energético y banca, prorrogado por Ley 7/2024'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-22684',
            ],
        ]);

        // Gravamen energéticas 1,2 % sobre cifra de negocios.
        $this->upsert('IMPUESTO_BONIF_BANCOS', 'state', null, [
            [
                'year' => 2025,
                'rate' => 1.2000,
                'conditions' => ['detalle' => 'gravamen sector energético: 1,2 % de la cifra de negocios anual de operadores con ingresos > 1.000 M€ en 2019'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-22684',
            ],
        ]);

        // IVPEE — 7 %.
        $this->upsert('IMPUESTO_ENERGETICO', 'state', null, [
            ['year' => 2025, 'rate' => 7.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15649'],
            ['year' => 2026, 'rate' => 7.0000, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15649'],
        ]);
    }

    private function seedRegionalImpuestos(): void
    {
        // ITP por CCAA top-4: rates resumen vigente 2025.
        $itpRates = [
            'MD' => [['year' => 2025, 'rate' => 6.0000, 'conditions' => ['detalle' => 'tipo general inmuebles']]],
            'CT' => [
                ['year' => 2025, 'rate' => 10.0000, 'conditions' => ['detalle' => 'inmuebles hasta 1 M€'], 'base_min' => 0, 'base_max' => 1000000],
                ['year' => 2025, 'rate' => 11.0000, 'conditions' => ['detalle' => 'tramo > 1 M€'], 'base_min' => 1000000.01],
            ],
            'AN' => [['year' => 2025, 'rate' => 7.0000, 'conditions' => ['detalle' => 'tipo único inmuebles tras reforma 2021']]],
            'VC' => [
                ['year' => 2025, 'rate' => 10.0000, 'conditions' => ['detalle' => 'tipo general inmuebles']],
                ['year' => 2025, 'rate' => 8.0000, 'conditions' => ['detalle' => 'vivienda habitual jóvenes / familias numerosas']],
            ],
        ];

        foreach ($itpRates as $region => $rates) {
            foreach ($rates as $r) {
                $this->upsert('ITP', 'regional', $region, [array_merge($r, [
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                ])]);
                // Replicar igual en 2026 con misma cuota (CCAA suelen mantener entre años salvo modificación).
                $this->upsert('ITP', 'regional', $region, [array_merge($r, [
                    'year' => 2026,
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                ])]);
            }
        }

        // AJD por CCAA top-4 — tipo gradual de la cuota notarial.
        $ajdRates = [
            'MD' => 0.7500,
            'CT' => 1.5000,
            'AN' => 1.2000,
            'VC' => 1.5000,
        ];
        foreach ($ajdRates as $region => $rate) {
            $this->upsert('AJD', 'regional', $region, [
                ['year' => 2025, 'rate' => $rate, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
                ['year' => 2026, 'rate' => $rate, 'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359'],
            ]);
        }

        // ISD bonificación efectiva (sucesiones Grupo II).
        $isdBonif = [
            'MD' => 99.0000,
            'CT' => 0.0000,
            'AN' => 99.0000,
            'VC' => 99.0000,
        ];
        foreach ($isdBonif as $region => $bonif) {
            $this->upsert('ISD', 'regional', $region, [
                [
                    'year' => 2025,
                    'rate' => $bonif,
                    'conditions' => ['tipo' => 'bonificacion_cuota_pct', 'grupo_parentesco' => 'I_II'],
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
                ],
                [
                    'year' => 2026,
                    'rate' => $bonif,
                    'conditions' => ['tipo' => 'bonificacion_cuota_pct', 'grupo_parentesco' => 'I_II'],
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
                ],
            ]);
        }

        // IP — bonificación cuota pct.
        $ipBonif = [
            'MD' => 100.0000,
            'CT' => 0.0000,
            'AN' => 100.0000,
            'VC' => 0.0000,
        ];
        foreach ($ipBonif as $region => $bonif) {
            $this->upsert('IP', 'regional', $region, [
                [
                    'year' => 2025,
                    'rate' => $bonif,
                    'conditions' => ['tipo' => 'bonificacion_cuota_pct'],
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-14392',
                ],
                [
                    'year' => 2026,
                    'rate' => $bonif,
                    'conditions' => ['tipo' => 'bonificacion_cuota_pct'],
                    'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-14392',
                ],
            ]);
        }
    }

    private function seedStateTasas(): void
    {
        $fixed = [
            ['code' => 'TASA_DNI', 'amount' => 12.00, 'source' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-7340'],
            ['code' => 'TASA_PASAPORTE', 'amount' => 30.00, 'source' => 'https://www.boe.es/buscar/doc.php?id=BOE-A-2010-12233'],
            ['code' => 'TASA_NIE', 'amount' => 9.84, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-19949'],
            ['code' => 'TASA_TITULO_UNIV_OFICIAL', 'amount' => 225.00, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-23936'],
            ['code' => 'TASA_TITULO_UNIV_PROPIO', 'amount' => 150.00, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-24515'],
            ['code' => 'TASA_BOE_PUBLICACION', 'amount' => 25.00, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-19949'],
            ['code' => 'TASA_OEPM_PATENTE', 'amount' => 92.30, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-8328'],
            ['code' => 'TASA_OEPM_MARCA', 'amount' => 144.58, 'source' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-23093'],
        ];

        foreach ($fixed as $f) {
            $this->upsert($f['code'], 'state', null, [
                [
                    'year' => 2025,
                    'rate' => null,
                    'fixed_amount' => $f['amount'],
                    'source_url' => $f['source'],
                ],
                [
                    'year' => 2026,
                    'rate' => null,
                    'fixed_amount' => $f['amount'],
                    'source_url' => $f['source'],
                ],
            ]);
        }

        // Tasas con cuantía variable (testigos).
        $this->upsert('TASA_JUDICIAL_PF', 'state', null, [
            [
                'year' => 2025,
                'rate' => null,
                'fixed_amount' => 0.00,
                'conditions' => ['detalle' => 'EXENTAS desde 2017 (RDL 3/2018, STC 140/2016)'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-14301',
            ],
        ]);

        $this->upsert('TASA_JUDICIAL_PJ', 'state', null, [
            [
                'year' => 2025,
                'rate' => 0.5000,
                'fixed_amount' => 300.00,
                'conditions' => ['detalle' => 'cuota fija 300 € + 0,5 % cuota variable, procedimiento ordinario verbal civil orientativo'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-14301',
            ],
            [
                'year' => 2026,
                'rate' => 0.5000,
                'fixed_amount' => 300.00,
                'conditions' => ['detalle' => 'cuota fija 300 € + 0,5 % cuota variable'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-14301',
            ],
        ]);

        $this->upsert('TASA_PORTUARIA_BUQUE', 'state', null, [
            [
                'year' => 2025,
                'rate' => null,
                'fixed_amount' => 0.0010,
                'conditions' => ['detalle' => 'cuantía básica orientativa T-1: 0,001 € por GT y hora — el cálculo final aplica coeficientes por puerto'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-16467',
            ],
        ]);

        $this->upsert('TASA_AEROPORTUARIA', 'state', null, [
            [
                'year' => 2025,
                'rate' => null,
                'fixed_amount' => 12.00,
                'conditions' => ['detalle' => 'cuantía orientativa por pasajero salida en aeropuerto categoría A — varía por DORA'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-7064',
            ],
        ]);

        $this->upsert('TASA_CNMV_INSCRIPCION', 'state', null, [
            [
                'year' => 2025,
                'rate' => 0.0300,
                'conditions' => ['detalle' => 'tasa por inscripción de folleto OPV: 0,03 % del importe colocado, mínimo y máximo según resolución anual'],
                'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-7064',
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function upsert(string $code, string $scope, ?string $regionCode, array $rates): void
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

        foreach ($rates as $row) {
            $row['tax_type_id'] = $type->id;
            $row['region_code'] = $row['region_code'] ?? $regionCode;
            $row['valid_from'] = $row['valid_from'] ?? sprintf('%d-01-01', (int) $row['year']);

            $where = [
                'tax_type_id' => $type->id,
                'year' => $row['year'],
                'region_code' => $row['region_code'],
                'rate' => $row['rate'] ?? null,
                'fixed_amount' => $row['fixed_amount'] ?? null,
            ];

            // Filtra valores null para evitar problemas con índices unique virtuales sobre conditions.
            TaxRate::updateOrCreate($where, $row);
        }
    }
}
