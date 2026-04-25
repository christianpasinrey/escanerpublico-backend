<?php

namespace Database\Seeders\Tax;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Tax\Enums\LevyType;
use Modules\Tax\Enums\Scope;
use Modules\Tax\Models\TaxType;

/**
 * Catálogo de las tasas estatales más frecuentes.
 *
 * Una tasa, en el sentido de la LGT 58/2003 art. 2.2.a, es una contraprestación
 * por la utilización privativa del dominio público o por la prestación de
 * servicios o realización de actividades en régimen de derecho público que
 * se refieran, afecten o beneficien de modo particular al obligado tributario.
 *
 * Estas tasas se cargan con cuantía fija (`fixed_amount`) en `tax_rates`
 * mediante `TaxRatesSeeder`. Las tasas con cuantía variable (judicial PJ,
 * portuarias, aeroportuarias) se modelan con condiciones complementarias.
 */
class TaxStateFeesSeeder extends Seeder
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
        $tasa = LevyType::Tasa->value;

        return [
            [
                'code' => 'TASA_DNI',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por expedición del DNI electrónico',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-13156',
                'editorial_md' => <<<'MD'
                    Tasa estatal exigida por la expedición del Documento Nacional de Identidad electrónico (DNIe).
                    Cuantía actualmente vigente: 12,00 € (según Orden INT/680/2010 actualizada por las Órdenes anuales de tasas).
                    Hecho imponible: la prestación del servicio de expedición o renovación del DNI.
                    Sujeto pasivo: el ciudadano solicitante.
                    Bonificaciones: gratuidad para familias numerosas y víctimas de terrorismo según condiciones legales.
                    Regulación: Ley Orgánica 4/2015 de Protección de la Seguridad Ciudadana, art. 8.
                    MD,
            ],
            [
                'code' => 'TASA_PASAPORTE',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por expedición del Pasaporte',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-13156',
                'editorial_md' => <<<'MD'
                    Tasa estatal por la prestación del servicio de expedición o renovación del pasaporte ordinario español.
                    Cuantía vigente: 30,00 € (Orden INT/2049/2010, actualizada por Órdenes anuales).
                    Hecho imponible: la expedición o renovación del documento.
                    Bonificaciones: gratuidad para familias numerosas y para mayores de 70 años.
                    Sujeto pasivo: el ciudadano solicitante.
                    Regulación: LO 4/2015 art. 11; Real Decreto 896/2003.
                    MD,
            ],
            [
                'code' => 'TASA_NIE',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por expedición de la Tarjeta de Identidad de Extranjero (TIE) o NIE',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-19949',
                'editorial_md' => <<<'MD'
                    Tasa estatal por la tramitación de autorizaciones de residencia y trabajo y por la expedición de los documentos asociados (NIE, TIE).
                    La tasa por asignación de NIE para extranjeros que no la requieran por motivos de residencia es de 9,84 € (modelo 790-012).
                    Hecho imponible: la tramitación administrativa de cada actuación.
                    Sujeto pasivo: el extranjero que solicita la documentación.
                    Modelos de autoliquidación: 790-012, 790-052 según trámite específico.
                    Regulación: Ley Orgánica 4/2000 sobre derechos y libertades de los extranjeros, art. 44.
                    MD,
            ],
            [
                'code' => 'TASA_JUDICIAL_PF',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por el ejercicio de la potestad jurisdiccional — Persona Física',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-14301',
                'editorial_md' => <<<'MD'
                    Tasa judicial estatal por el ejercicio de la potestad jurisdiccional en el orden civil, contencioso-administrativo y social.
                    Tras la STC 140/2016 y la reforma de 2017, las personas físicas están EXENTAS de la tasa judicial en todos los órdenes.
                    Antes de la exención, la cuota fija oscilaba entre 100 y 1.200 € según orden y procedimiento, con un componente variable del 0,1 % o 0,5 %.
                    Mantenida en el catálogo por trazabilidad histórica y para casos residuales (procedimientos pendientes anteriores a la reforma).
                    Modelo: 696 (autoliquidación) y 695 (solicitud de devolución).
                    Regulación: Ley 10/2012, modificada por RDL 1/2015 y RDL 3/2018.
                    MD,
            ],
            [
                'code' => 'TASA_JUDICIAL_PJ',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por el ejercicio de la potestad jurisdiccional — Persona Jurídica',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2012-14301',
                'editorial_md' => <<<'MD'
                    Tasa judicial estatal exigida a personas jurídicas (no a personas físicas, que están exentas) por el ejercicio de la potestad jurisdiccional.
                    Cuota fija: 100 a 1.200 € según orden y procedimiento (verbales, ordinarios, monitorios, ejecuciones, recursos de apelación, casación).
                    Cuota variable: 0,1 % a 0,5 % de la base imponible (cuantía del procedimiento), con un máximo de 10.000 €.
                    Sujeto pasivo: persona jurídica que promueve la actuación judicial gravada.
                    Reducción del 10 % para presentación telemática.
                    Modelo: 696 (autoliquidación). Regulación: Ley 10/2012, art. 1-11.
                    MD,
            ],
            [
                'code' => 'TASA_TITULO_UNIV_OFICIAL',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por expedición de título universitario oficial',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2003-23936',
                'editorial_md' => <<<'MD'
                    Tasa estatal abonada por el alumno al solicitar la expedición de un título universitario de carácter oficial (Grado, Máster, Doctorado).
                    Cuantías orientativas (varían anualmente por Ley de Presupuestos): Grado ~225 €, Máster ~225 €, Doctorado ~290 €, Diploma de Especialización ~150 €.
                    Las CCAA fijan adicionalmente la "tasa académica" autonómica por crédito (no incluida aquí).
                    Bonificaciones: 50 % familia numerosa general, 100 % familia numerosa especial, víctimas de violencia de género.
                    Modelo: 790 universidad correspondiente.
                    Regulación: Ley Orgánica 6/2001 de Universidades art. 81 y disposición adicional sexta.
                    MD,
            ],
            [
                'code' => 'TASA_TITULO_UNIV_PROPIO',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa por expedición de título universitario propio',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-24515',
                'editorial_md' => <<<'MD'
                    Tasa académica satisfecha por la expedición de títulos propios universitarios (Experto, Especialista, Máster propio).
                    Cuantía fijada por cada universidad pública en su normativa propia, normalmente entre 100 € y 250 €.
                    No es una tasa estatal estricta sino "precio público académico" cuya horquilla viene autorizada por la CCAA.
                    Sujeto pasivo: estudiante solicitante del título.
                    Modelo: variable según universidad.
                    Regulación: art. 34.3 LOU + RD 822/2021 de organización de las enseñanzas universitarias.
                    MD,
            ],
            [
                'code' => 'TASA_PORTUARIA_BUQUE',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa portuaria T-1 sobre buques (Puertos del Estado)',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-16467',
                'editorial_md' => <<<'MD'
                    Tasa estatal portuaria T-1 que grava la utilización de las aguas portuarias y de las obras e instalaciones portuarias por buques mercantes y de pasaje.
                    La cuota se calcula multiplicando la cuantía básica (€ por GT × hora) por el GT del buque y por el tiempo de estancia, con coeficientes correctores por puerto y modalidad.
                    Sujeto pasivo: la naviera o consignatario del buque que utiliza el puerto.
                    El tipo y los coeficientes los fija anualmente la Ley de Presupuestos Generales del Estado.
                    No es una tasa de cuantía fija; el dato cargado en `tax_rates` es indicativo y necesita complementarse con el cálculo específico.
                    Regulación: TR Ley de Puertos del Estado RDL 2/2011, arts. 192-204.
                    MD,
            ],
            [
                'code' => 'TASA_AEROPORTUARIA',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Prestación patrimonial pública aeroportuaria — Aterrizaje y pasajeros',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-7064',
                'editorial_md' => <<<'MD'
                    Conjunto de prestaciones patrimoniales públicas no tributarias gestionadas por AENA (sociedad mercantil estatal) que gravan aterrizaje, salida de pasajeros, estacionamiento y servicios aeroportuarios asociados.
                    Tras la Ley 18/2014 dejaron de ser técnicamente tasas y se convirtieron en "prestaciones patrimoniales públicas no tributarias" — incluidas aquí por homogeneidad funcional para el catálogo público.
                    Cuantía: variable por aeropuerto (clasificación A, B, C, D), MTOW del avión, número de pasajeros y franja horaria.
                    Sujeto pasivo: las compañías aéreas operadoras.
                    Las tarifas vigentes se aprueban en el DORA (Documento de Regulación Aeroportuaria) quinquenal.
                    Regulación: Ley 18/2014, de 15 de octubre, capítulo IX.
                    MD,
            ],
            [
                'code' => 'TASA_CNMV_INSCRIPCION',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa CNMV — Inscripción y registro',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-7064',
                'editorial_md' => <<<'MD'
                    Tasa estatal por servicios prestados por la Comisión Nacional del Mercado de Valores: inscripción de folletos, admisión a cotización, registro de fondos y de gestoras, supervisión de OPV/OPS.
                    Cuantía variable por tipo de inscripción y volumen del emisor; por ejemplo: registro de folleto OPV ≈ 0,03 % del importe colocado, con mínimo y máximo.
                    Sujeto pasivo: emisor o gestora que solicita la inscripción.
                    Periodicidad: por acto registral y, en su caso, anualmente para gestoras (cuota de supervisión).
                    Regulación: Ley 18/2014, capítulo IX y Resolución CNMV anual de tarifas.
                    MD,
            ],
            [
                'code' => 'TASA_BOE_PUBLICACION',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa BOE — Inserción de anuncios',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-19949',
                'editorial_md' => <<<'MD'
                    Tasa estatal por la publicación de anuncios obligatorios en el Boletín Oficial del Estado y en el BORME (Boletín Oficial del Registro Mercantil).
                    Cuantía: variable por extensión y modalidad (caracteres, columnas, pliego); BORME tiene escala propia para cuentas anuales y actos societarios.
                    Sujeto pasivo: la persona física o jurídica que solicita la inserción.
                    Bonificaciones: hay tarifas reducidas para inserciones de PYMES y para anuncios de carácter judicial obligatorio.
                    Modelo: 791 (autoliquidación BOE).
                    Regulación: Ley 5/2002 reguladora del BOE; Orden PRE anual de tarifas.
                    MD,
            ],
            [
                'code' => 'TASA_OEPM_PATENTE',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa OEPM — Solicitud y mantenimiento de patentes',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-8328',
                'editorial_md' => <<<'MD'
                    Conjunto de tasas exigidas por la Oficina Española de Patentes y Marcas por la tramitación y mantenimiento de patentes nacionales.
                    Cuantías principales (Ley 24/2015): solicitud 92,30 €; búsqueda internacional 700-1.800 €; mantenimiento anual creciente del 3.º al 20.º año.
                    Bonificación del 50 % para emprendedores autónomos, PYMES y Universidades públicas durante los 3 primeros años.
                    Sujeto pasivo: solicitante o titular de la patente.
                    Modelo: 791 OEPM.
                    Regulación: Ley 24/2015 de Patentes, anexo de tasas.
                    MD,
            ],
            [
                'code' => 'TASA_OEPM_MARCA',
                'scope' => $scope,
                'levy_type' => $tasa,
                'name' => 'Tasa OEPM — Solicitud y renovación de marcas',
                'base_law_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2001-23093',
                'editorial_md' => <<<'MD'
                    Tasas OEPM por solicitud, registro y renovación de marcas nacionales y nombres comerciales.
                    Cuantía principal: solicitud de marca en una clase 144,58 €; renovación 153,71 €; tasa por clase adicional 93,68 € (importes 2025).
                    Sujeto pasivo: solicitante o titular de la marca.
                    Bonificaciones: presentación electrónica reducción del 15 % aproximada.
                    Modelo: 791 OEPM.
                    Regulación: Ley 17/2001 de Marcas, anexo de tasas.
                    MD,
            ],
        ];
    }
}
