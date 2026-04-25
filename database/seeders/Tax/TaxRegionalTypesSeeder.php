<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxType;

/**
 * Catálogo de impuestos cedidos a las 4 CCAA de mayor población (MD/CT/AN/VC).
 *
 * Para cada CCAA se siembran las versiones "regional" de los tributos cedidos
 * (ITP, AJD, ISD, IP) y los tributos propios autonómicos relevantes
 * (Tributos sobre el Juego, Tasa fiscal sobre operadores ASJ y, donde aplique,
 * Tasa Hidrocarburos Minorista — figura asimilada a tipo autonómico
 * tras la integración del antiguo IVMDH).
 *
 * Las CCAA tienen capacidad normativa cedida (LOFCA + Ley 22/2009)
 * para fijar tipos, deducciones y bonificaciones. Los tipos efectivos
 * vigentes se cargan en `tax_rates` mediante `TaxRatesSeeder`.
 */
class TaxRegionalTypesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->definitions() as $row) {
            TaxType::updateOrCreate(
                [
                    'code' => $row['code'],
                    'scope' => $row['scope'],
                    'region_code' => $row['region_code'],
                ],
                $row,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function definitions(): array
    {
        $regiones = [
            'MD' => 'Madrid',
            'CT' => 'Cataluña',
            'AN' => 'Andalucía',
            'VC' => 'Comunidad Valenciana',
        ];

        $defs = [];
        foreach ($regiones as $code => $nombre) {
            $defs = array_merge($defs, $this->forRegion($code, $nombre));
        }

        return $defs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function forRegion(string $regionCode, string $regionName): array
    {
        $scope = Scope::Regional->value;
        $impuesto = LevyType::Impuesto->value;
        $tasa = LevyType::Tasa->value;

        $base = [
            [
                'code' => 'ITP',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Impuesto sobre Transmisiones Patrimoniales (TPO) — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'editorial_md' => $this->itpEditorial($regionCode, $regionName),
            ],
            [
                'code' => 'AJD',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Impuesto sobre Actos Jurídicos Documentados — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'editorial_md' => $this->ajdEditorial($regionCode, $regionName),
            ],
            [
                'code' => 'ISD',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Impuesto sobre Sucesiones y Donaciones — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
                'editorial_md' => $this->isdEditorial($regionCode, $regionName),
            ],
            [
                'code' => 'IP',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Impuesto sobre el Patrimonio — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-14392',
                'editorial_md' => $this->ipEditorial($regionCode, $regionName),
            ],
            [
                'code' => 'TRIB_JUEGO',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Tributos sobre el Juego — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1977-26470',
                'editorial_md' => <<<MD
                    Conjunto de tributos cedidos a {$regionName} que gravan la celebración de juegos de azar (casinos, bingo, máquinas recreativas tipo "B" y "C") y la recaudación de apuestas.
                    Las CCAA tienen amplia capacidad normativa para fijar tipos y cuotas: tipos por trimestre, cuotas fijas por máquina, tipo sobre cantidad jugada o recaudada.
                    Sujeto pasivo: operadores de juego presencial autorizados por la propia CCAA.
                    Modelos autonómicos: 044 (juegos), 042 (máquinas), 043 (bingos).
                    Regulación: RD-Ley 16/1977, de 25 de febrero, y normativa autonómica de tasas sobre el juego.
                    MD,
            ],
            [
                'code' => 'TASA_ASJ',
                'scope' => $scope,
                'levy_type' => $tasa,
                'region_code' => $regionCode,
                'name' => "Tasa fiscal sobre operadores de apuestas y juego online — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-9419',
                'editorial_md' => <<<MD
                    Tasa autonómica que grava la actividad de los operadores de apuestas y juego online con licencia para operar en {$regionName}.
                    Hecho imponible: la realización de actividades de juego de competencia autonómica.
                    La base imponible suele ser la cantidad bruta jugada o el GGR (gross gaming revenue).
                    Es una figura complementaria al impuesto estatal sobre actividades de juego de la Ley 13/2011 (ámbito nacional).
                    Sujeto pasivo: operadores autorizados por la autoridad reguladora autonómica.
                    Regulación: Ley 13/2011, de 27 de mayo, de regulación del juego, y normativa autonómica.
                    MD,
            ],
        ];

        if ($regionCode === 'CT') {
            // Cataluña tradicionalmente recargo IRPF mantiene su tramo regional alineado en su escala propia.
            // Mantenemos solo los figurines obligatorios — recargos IRPF se modelan vía tax_brackets en M3.
        }

        if ($regionCode !== 'CT') {
            // El antiguo Impuesto sobre Ventas Minoristas de Determinados Hidrocarburos (IVMDH)
            // fue derogado en 2013 e integrado en el Impuesto sobre Hidrocarburos como tipo
            // autonómico (Ley 2/2012). Lo conservamos como figura "tasa autonómica" en las
            // CCAA donde se aplicó (todas excepto Cataluña por motivos de fiscalidad propia).
            $base[] = [
                'code' => 'TASA_HIDROCARBUROS_MINORISTA',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'region_code' => $regionCode,
                'name' => "Tipo autonómico del Impuesto sobre Hidrocarburos — {$regionName}",
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
                'editorial_md' => <<<MD
                    Tramo autonómico del Impuesto Especial sobre Hidrocarburos cuyo rendimiento se cede a {$regionName}.
                    Sustituyó al antiguo Impuesto sobre Ventas Minoristas de Determinados Hidrocarburos (IVMDH) tras su integración por la Ley 2/2012.
                    Cada CCAA fija el tipo de gravamen autonómico dentro de los límites legales (0 a 48 €/1.000 l para gasolinas y gasóleos de uso general).
                    El tipo autonómico se suma al tipo estatal para determinar la cuota total devengada.
                    Sujeto pasivo: depositarios autorizados; recaudación incorporada a la liquidación estatal del modelo 581.
                    Regulación: Ley 38/1992 art. 50 ter, según redacción dada por la Ley 2/2012.
                    MD,
            ];
        }

        return $base;
    }

    private function itpEditorial(string $regionCode, string $regionName): string
    {
        $tipoNote = match ($regionCode) {
            'MD' => 'Madrid mantiene el tipo general del 6 % para inmuebles, uno de los más bajos del país.',
            'CT' => 'Cataluña aplica una escala progresiva con tipo medio del 10 % para inmuebles de valor medio-alto.',
            'AN' => 'Andalucía tiene un tipo único del 7 % desde la reforma de 2021, con bonificaciones específicas.',
            'VC' => 'La Comunidad Valenciana aplica un tipo del 10 % para inmuebles, con tipo reducido del 8 % para vivienda habitual de jóvenes y familias numerosas.',
            default => "{$regionName} aplica los tipos definidos en su normativa de tributos cedidos.",
        };

        return <<<MD
            Modalidad de Transmisiones Patrimoniales Onerosas del ITP cedida a {$regionName}.
            Grava las transmisiones patrimoniales entre particulares (no sujetas a IVA) y la constitución de derechos reales, préstamos, fianzas, arrendamientos y pensiones.
            {$tipoNote}
            La capacidad normativa cedida permite a {$regionName} regular tipos, reducciones y bonificaciones (Ley 22/2009, de cesión).
            Hecho imponible: transmisiones onerosas y constitución de derechos sobre bienes situados en {$regionName} o a favor de residentes.
            Modelo: 600 autonómico, presentado en la oficina liquidadora correspondiente.
            MD;
    }

    private function ajdEditorial(string $regionCode, string $regionName): string
    {
        return <<<MD
            Modalidad de Actos Jurídicos Documentados del impuesto cedido a {$regionName}.
            Grava los documentos notariales, mercantiles y administrativos con acceso a registros públicos.
            La cuota gradual sobre escrituras notariales tiene el tipo fijado por {$regionName} dentro de la horquilla autorizada.
            Tipos habituales: 1,2 % a 1,5 % para vivienda habitual con bonificaciones; 1,5 % a 2 % para resto de inmuebles.
            Hecho imponible: formalización de documentos notariales valorables y con efectos registrales.
            Regulación: RD Legislativo 1/1993 y norma autonómica de medidas fiscales.
            MD;
    }

    private function isdEditorial(string $regionCode, string $regionName): string
    {
        $bonifNote = match ($regionCode) {
            'MD' => 'Madrid bonifica al 99 % la cuota para descendientes, ascendientes y cónyuge (Grupos I y II), tanto en sucesiones como en donaciones.',
            'CT' => 'Cataluña aplica reducciones generales y específicas; tipos progresivos del 7 % al 32 % en sucesiones para Grupo II.',
            'AN' => 'Andalucía bonifica al 99 % para Grupos I y II en sucesiones y donaciones desde 2019, con condiciones de residencia.',
            'VC' => 'La Comunidad Valenciana bonifica al 99 % para Grupos I y II en sucesiones tras la Ley 6/2023.',
            default => "{$regionName} aplica las bonificaciones y reducciones definidas en su normativa.",
        };

        return <<<MD
            Versión autonómica del Impuesto sobre Sucesiones y Donaciones aplicable a herederos y donatarios residentes en {$regionName} o sobre bienes radicados en {$regionName}.
            {$bonifNote}
            Hecho imponible: adquisición mortis causa, donación inter vivos y percepción de seguros de vida con beneficiario distinto del contratante.
            Base imponible: valor neto de los bienes y derechos adquiridos, según valor de referencia del Catastro.
            La capacidad normativa cedida permite a {$regionName} regular reducciones, tipos y bonificaciones (Ley 22/2009).
            Modelos: 650 sucesiones, 651 donaciones (en su versión autonómica).
            MD;
    }

    private function ipEditorial(string $regionCode, string $regionName): string
    {
        $bonifNote = match ($regionCode) {
            'MD' => 'Madrid bonifica al 100 % la cuota del Impuesto sobre el Patrimonio: en la práctica los residentes no tributan, salvo por el Impuesto Temporal de Solidaridad de las Grandes Fortunas (estatal).',
            'CT' => 'Cataluña aplica una escala propia con tipo marginal del 3,03 % para patrimonios > 10,7 M€.',
            'AN' => 'Andalucía bonifica al 100 % desde septiembre 2022; los residentes no tributan en IP, solo en su caso por el ITSGF estatal.',
            'VC' => 'La Comunidad Valenciana aplica una escala con tipo marginal del 3,5 % y mínimo exento de 500.000 €.',
            default => "{$regionName} aplica los tipos definidos en su normativa de tributos cedidos.",
        };

        return <<<MD
            Versión autonómica del Impuesto sobre el Patrimonio aplicable a residentes en {$regionName}.
            {$bonifNote}
            Hecho imponible: titularidad por una persona física del patrimonio neto a 31 de diciembre de cada año.
            Base imponible: valor del patrimonio neto del sujeto pasivo (activos menos pasivos deducibles).
            Mínimo exento estatal: 700.000 €; algunas CCAA elevan o reducen este mínimo.
            Modelo: 714 (autoliquidación anual), presentado junto con la declaración del IRPF.
            Regulación: Ley 19/1991, de 6 de junio, y normativa autonómica de cesión.
            MD;
    }
}
