<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxType;

/**
 * Catálogo enciclopédico de los impuestos estatales españoles.
 *
 * Cada entrada lleva el enlace al BOE consolidado (legislación vigente
 * tras todas las modificaciones publicadas) y un editorial_md de 5-10
 * líneas en lenguaje plano explicando hecho imponible, sujetos pasivos,
 * base imponible y ámbito de aplicación.
 *
 * Fuentes:
 *  - https://www.boe.es/buscar/legislacion.php
 *  - AEAT manual práctico de cada figura
 */
class TaxStateTypesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->definitions() as $row) {
            TaxType::updateOrCreate(
                [
                    'code' => $row['code'],
                    'scope' => $row['scope'],
                    'region_code' => $row['region_code'] ?? null,
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
        $scope = Scope::State->value;
        $impuesto = LevyType::Impuesto->value;

        return [
            [
                'code' => 'IRPF',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre la Renta de las Personas Físicas',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764',
                'editorial_md' => <<<'MD'
                    El IRPF grava la renta obtenida por personas físicas residentes en España, con independencia de dónde se haya generado.
                    Tiene carácter personal, directo, subjetivo y progresivo: cuanto mayor es la base liquidable, mayor es el tipo aplicable.
                    Se estructura en una parte estatal y otra autonómica, ambas con escalas propias que las CCAA pueden modificar dentro de un margen.
                    Hecho imponible: obtención de rendimientos del trabajo, capital, actividades económicas y ganancias y pérdidas patrimoniales.
                    Período impositivo: año natural; modelo anual 100, retenciones a cuenta vía empleadores y modelos 130/131 para autónomos.
                    Su regulación principal está en la Ley 35/2006, de 28 de noviembre, y el Reglamento RD 439/2007.
                    MD,
            ],
            [
                'code' => 'IS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre Sociedades',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
                'editorial_md' => <<<'MD'
                    El Impuesto sobre Sociedades grava la renta obtenida por las personas jurídicas y otras entidades residentes en España.
                    El tipo general es del 25 %, con tipos reducidos para empresas de reducida dimensión, microempresas, cooperativas y entidades de nueva creación.
                    Hecho imponible: la obtención de renta por el contribuyente, sea cual sea su fuente u origen.
                    Base imponible: resultado contable ajustado por las correcciones fiscales previstas en la Ley 27/2014.
                    Modelo anual: 200; pagos fraccionados: modelo 202 (tres veces al año, abril, octubre y diciembre).
                    Regulación: Ley 27/2014, de 27 de noviembre, y Reglamento RD 634/2015.
                    MD,
            ],
            [
                'code' => 'IVA',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre el Valor Añadido',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
                'editorial_md' => <<<'MD'
                    El IVA es un impuesto indirecto que grava el consumo, recayendo sobre las entregas de bienes y prestaciones de servicios realizadas por empresarios o profesionales.
                    Es un impuesto neutral para las empresas y soportado por el consumidor final mediante el mecanismo de repercusión y deducción.
                    Tipos vigentes: general 21 %, reducido 10 %, superreducido 4 %; existe un tipo especial 5 % aplicado coyunturalmente a determinados alimentos básicos por RD-Ley.
                    Hecho imponible: entregas de bienes y servicios, adquisiciones intracomunitarias e importaciones realizadas en el TAI.
                    Modelos: 303 (autoliquidación trimestral), 390 (resumen anual), 349 (intracomunitario), 369 (OSS).
                    Regulación: Ley 37/1992, de 28 de diciembre, y Reglamento RD 1624/1992.
                    MD,
            ],
            [
                'code' => 'ITP',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre Transmisiones Patrimoniales (modalidad TPO)',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'editorial_md' => <<<'MD'
                    El ITP en su modalidad de Transmisiones Patrimoniales Onerosas (TPO) grava las transmisiones patrimoniales entre particulares y la constitución de derechos reales, préstamos, fianzas, arrendamientos y pensiones.
                    Es un tributo cedido a las Comunidades Autónomas, que tienen capacidad normativa para fijar los tipos: las versiones autonómicas se publican como `ITP` con scope `regional`.
                    Hecho imponible: transmisiones onerosas no sujetas a IVA y constitución de derechos reales sobre bienes.
                    Base imponible: valor del bien o derecho transmitido, según el "valor de referencia" del Catastro.
                    Modelo: 600 autonómico, presentado en la oficina liquidadora de la CCAA donde radique el bien.
                    Regulación: RD Legislativo 1/1993, de 24 de septiembre.
                    MD,
            ],
            [
                'code' => 'AJD',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre Actos Jurídicos Documentados',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1993-25359',
                'editorial_md' => <<<'MD'
                    El AJD grava los documentos notariales, mercantiles y administrativos que tienen acceso a registros públicos y producen efectos jurídicos.
                    Es un tributo cedido a las CCAA, que fijan el tipo de la cuota gradual sobre documentos notariales (escrituras de compraventa, hipoteca, obra nueva…).
                    Modalidades: cuota fija (timbre del papel), cuota gradual (% sobre la base) y cuota variable.
                    Base imponible (cuota gradual): valor declarado en el documento.
                    Sujeto pasivo en préstamos hipotecarios: la entidad prestamista (RDL 17/2018).
                    Regulación: RD Legislativo 1/1993, de 24 de septiembre, arts. 27-44.
                    MD,
            ],
            [
                'code' => 'ISD',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre Sucesiones y Donaciones',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1987-28141',
                'editorial_md' => <<<'MD'
                    El ISD grava los incrementos patrimoniales obtenidos a título lucrativo (gratuito) por personas físicas: herencias, legados, donaciones y seguros de vida.
                    Es un tributo cedido a las CCAA, que aplican grandes bonificaciones y reducciones según parentesco; las versiones autonómicas se publican con scope `regional`.
                    Hecho imponible: adquisición mortis causa, donación inter vivos, percepción de seguro de vida del que el contratante es persona distinta del beneficiario.
                    Base imponible: valor neto de los bienes y derechos adquiridos.
                    Modelos: 650 sucesiones, 651 donaciones.
                    Regulación: Ley 29/1987, de 18 de diciembre, y Reglamento RD 1629/1991.
                    MD,
            ],
            [
                'code' => 'IIEE_HIDROCARBUROS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto Especial sobre Hidrocarburos',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
                'editorial_md' => <<<'MD'
                    Impuesto especial monofásico que grava la fabricación, importación y, en su caso, la introducción en el ámbito territorial interno de hidrocarburos.
                    Hecho imponible: salida de fábrica o depósito fiscal de productos como gasolinas, gasóleos, fuelóleos, GLP, gas natural y biocarburantes.
                    Base imponible: el volumen del producto a temperatura de 15 °C; el tipo se expresa en €/1000 litros.
                    Existen tipos diferenciados por uso (general, profesional, calefacción) y reducciones para determinados sectores.
                    Modelo: 581 (autoliquidación) y 582 (operaciones específicas).
                    Regulación: Ley 38/1992, de 28 de diciembre, capítulo VII.
                    MD,
            ],
            [
                'code' => 'IIEE_ALCOHOL',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuestos Especiales sobre el Alcohol y Bebidas Alcohólicas',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
                'editorial_md' => <<<'MD'
                    Conjunto de impuestos especiales que gravan la fabricación e importación de cerveza, vino y bebidas fermentadas, productos intermedios y alcohol y bebidas derivadas.
                    Hecho imponible: salida de fábrica o depósito fiscal de los productos sujetos.
                    Base imponible: hectolitros de producto o de alcohol puro según la figura.
                    Las bebidas alcohólicas se gravan en función de su graduación; el vino tributa al tipo cero pero está sometido a controles formales.
                    Modelos: 553/557 cerveza, 555/559 vino, 561/562/563 alcohol y bebidas derivadas.
                    Regulación: Ley 38/1992, de 28 de diciembre, capítulos II-VI.
                    MD,
            ],
            [
                'code' => 'IIEE_TABACO',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre las Labores del Tabaco',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
                'editorial_md' => <<<'MD'
                    Impuesto especial monofásico que grava la fabricación, importación y entrada en territorio nacional de cigarrillos, cigarros, picadura y otras labores del tabaco.
                    Hecho imponible: salida de fábrica o depósito fiscal de las labores.
                    La cuota se calcula combinando un tipo proporcional sobre el precio de venta al público y un tipo específico por unidad o peso, con un mínimo recaudatorio.
                    El tipo proporcional sobre cigarrillos es del 51 % del PVP (Ley 11/2021).
                    Modelos: 566 (resumen anual) y 580 (declaración mensual).
                    Regulación: Ley 38/1992, de 28 de diciembre, capítulo VIII.
                    MD,
            ],
            [
                'code' => 'IIEE_ELECTRICIDAD',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto Especial sobre la Electricidad',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28741',
                'editorial_md' => <<<'MD'
                    Impuesto especial que grava el suministro de energía eléctrica para consumo y el autoconsumo por los productores.
                    Es un impuesto monofásico que repercute sobre el consumidor final mediante la factura eléctrica.
                    Tipo general: 5,11269632 % sobre la base imponible (energía facturada × precio), con mínimo de 0,5 €/MWh para uso industrial y 1 €/MWh para uso doméstico.
                    Reducciones específicas para grandes consumidores industriales, riego agrícola y procesos electrointensivos.
                    Modelo: 560 (autoliquidación trimestral o mensual).
                    Regulación: Ley 38/1992, de 28 de diciembre, capítulo IX.
                    MD,
            ],
            [
                'code' => 'IIEE_PRIMAS_SEGUROS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre las Primas de Seguros',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1996-29117',
                'editorial_md' => <<<'MD'
                    Impuesto indirecto que grava las operaciones de seguro y capitalización en territorio español, recayendo sobre el importe de la prima.
                    Tipo vigente: 8 % desde el 1 de enero de 2021 (Ley 11/2020 de PGE).
                    Hecho imponible: realización de operaciones de seguro y capitalización por entidades aseguradoras.
                    Exenciones: seguros sociales obligatorios, planes de pensiones, seguros de vida, asistencia sanitaria y reaseguro.
                    Modelo: 480 (declaración mensual) y 430 (autoliquidación).
                    Regulación: art. 12 de la Ley 13/1996, de 30 de diciembre.
                    MD,
            ],
            [
                'code' => 'IGIC',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto General Indirecto Canario',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-15021',
                'editorial_md' => <<<'MD'
                    Tributo indirecto análogo al IVA aplicable exclusivamente en las Islas Canarias, que constituye su régimen fiscal especial junto con el AIEM.
                    Hecho imponible: entregas de bienes y prestaciones de servicios realizadas por empresarios y profesionales en Canarias.
                    Tipos: cero (productos básicos), reducido 3 %, general 7 %, incrementado 9,5 % y especiales 15 % y 20 % (combustibles, tabaco).
                    Lo gestiona la Agencia Tributaria Canaria; recaudación cedida íntegramente a la CA Canaria, Cabildos y municipios.
                    Modelos: 412/417/420 según operador.
                    Regulación: Ley 20/1991, de 7 de junio, modificada por Ley 4/2012.
                    MD,
            ],
            [
                'code' => 'IPSI',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre la Producción, los Servicios y la Importación (Ceuta y Melilla)',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1991-26233',
                'editorial_md' => <<<'MD'
                    Impuesto indirecto exclusivo de Ceuta y Melilla análogo al IVA pero con tipos reducidos y figuras propias.
                    Hecho imponible: producción e importación de bienes muebles corporales, prestaciones de servicios y consumo de electricidad.
                    Tipos: oscilan entre 0,5 % y 10 % según producto y servicio; combustibles y tabaco con tipos diferenciados.
                    Lo gestionan las propias ciudades autónomas y constituye su principal fuente de ingresos tributarios.
                    Modelos: variables según ciudad y figura específica (importación, producción, servicios).
                    Regulación: Ley 8/1991, de 25 de marzo.
                    MD,
            ],
            [
                'code' => 'IMPUESTO_PLASTICOS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto especial sobre los envases de plástico no reutilizables',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-5809',
                'editorial_md' => <<<'MD'
                    Impuesto medioambiental indirecto que grava la fabricación, importación o adquisición intracomunitaria de envases de plástico no reutilizables, semielaborados y productos plásticos destinados a contener, proteger, manipular, distribuir o presentar mercancías.
                    Vigente desde el 1 de enero de 2023.
                    Tipo: 0,45 €/kg de plástico no reciclado contenido en el envase.
                    Sujeto pasivo: fabricantes, importadores y adquirentes intracomunitarios.
                    Modelo: 592 (autoliquidación trimestral).
                    Regulación: Ley 7/2022, de 8 de abril, de residuos y suelos contaminados, art. 67-83.
                    MD,
            ],
            [
                'code' => 'IMPUESTO_DEPOSITO_BANCOS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre los Depósitos en las Entidades de Crédito',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15212',
                'editorial_md' => <<<'MD'
                    Impuesto directo de carácter estatal que grava la mera tenencia de depósitos constituidos por la clientela en entidades de crédito.
                    Tipo: 0,03 % sobre la base imponible (saldo medio del último período).
                    Sujeto pasivo: las entidades de crédito que operan en territorio español, cualquiera que sea su forma jurídica y nacionalidad.
                    No es repercutible al cliente.
                    Modelo: 411 (autoliquidación) y 410 (pago a cuenta).
                    Regulación: art. 19 de la Ley 16/2012, de 27 de diciembre, modificado por Ley 11/2013 y Ley 18/2014.
                    MD,
            ],
            [
                'code' => 'IMPUESTO_BONIF_BANCOS',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Gravamen Temporal Energético y de Entidades de Crédito (Ley 38/2022)',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-22684',
                'editorial_md' => <<<'MD'
                    Conjunto de prestaciones patrimoniales no tributarias de carácter temporal que gravan los ingresos de grandes empresas energéticas y entidades de crédito.
                    Banca: 4,8 % sobre la suma de margen de intereses y comisiones netas; aplicable a entidades con ingresos superiores a 800 M€ en 2019.
                    Energía: 1,2 % sobre la cifra de negocios anual de operadores con ingresos superiores a 1.000 M€ en 2019.
                    Diseñado inicialmente como temporal (2023-2024), en 2024 se prorrogó y reformuló como impuesto permanente sobre el margen de intereses y comisiones (Ley 7/2024).
                    Sujeto pasivo: bancos y energéticas grandes; no es repercutible al cliente.
                    Regulación: Ley 38/2022, de 27 de diciembre, modificada por Ley 7/2024.
                    MD,
            ],
            [
                'code' => 'IMPUESTO_ENERGETICO',
                'scope' => $scope,
                'levy_type' => $impuesto,
                'name' => 'Impuesto sobre el Valor de la Producción de Energía Eléctrica (IVPEE)',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-15649',
                'editorial_md' => <<<'MD'
                    Impuesto directo de carácter estatal que grava la realización de actividades de producción e incorporación al sistema eléctrico de energía eléctrica medida en barras de central.
                    Tipo: 7 % sobre el importe total ingresado por el productor.
                    Sujetos pasivos: productores que vierten energía a la red de transporte o distribución.
                    Suspendido temporalmente entre 2018 y 2024 mediante diversos RDL para mitigar el alza del precio eléctrico; restablecido al 7 % desde el segundo trimestre de 2024.
                    Modelo: 583 (autoliquidación trimestral) y 588 (pagos fraccionados).
                    Regulación: Ley 15/2012, de 27 de diciembre, art. 8.
                    MD,
            ],
        ];
    }
}
