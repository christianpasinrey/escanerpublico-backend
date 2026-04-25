<?php

namespace Modules\Tax\database\seeders\catalog;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxRegime;

/**
 * Seeder del catálogo completo de regímenes tributarios españoles.
 *
 * Cubre los 31 códigos definidos en RegimeCode::all():
 *  - IRPF: EDN, EDS, EO, AR, ASALARIADO_GEN
 *  - IVA: GEN, CAJA, SIMPLE, RE, REAGP, REBU, AAVV, ISP, OIC, OSS, EXENTO
 *  - IS: GEN, ERD, MICRO, STARTUP, COOP, SOCIMI, SICAV, CONSOL, ESFL
 *  - SS: RG, RETA, AGRARIO, HOGAR, MAR, MINERIA
 *
 * Idempotente: usa updateOrCreate por (scope, code).
 *
 * Cada régimen incluye:
 *  - description corta (1 línea)
 *  - requirements JSON con umbrales y exclusiones legales actuales
 *  - model_quarterly y model_annual (cuando aplique)
 *  - legal_reference_url al BOE consolidado
 *  - editorial_md plain language explicativo (5-15 líneas)
 *
 * Fuentes:
 *  - Ley 35/2006 IRPF y RD 439/2007 Reglamento (consolidados BOE)
 *  - Ley 37/1992 IVA y RD 1624/1992 Reglamento (consolidados BOE)
 *  - Ley 27/2014 IS (consolidado BOE)
 *  - RD-Leg 8/2015 LGSS y RD 2064/1995 Reglamento general (consolidados BOE)
 */
class TaxRegimesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->regimes() as $attributes) {
            TaxRegime::query()->updateOrCreate(
                ['scope' => $attributes['scope'], 'code' => $attributes['code']],
                $attributes,
            );
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function regimes(): iterable
    {
        // ============================================================
        // IRPF (5)
        // ============================================================
        yield [
            'code' => 'EDN',
            'scope' => 'irpf',
            'name' => 'Estimación Directa Normal (IRPF)',
            'description' => 'Régimen general para empresarios y profesionales con ingresos > 600.000 € o renuncia a EDS.',
            'requirements' => [
                'turnover_threshold_max' => null,
                'turnover_threshold_min' => 600000,
                'incompatibilities' => ['EDS', 'EO'],
                'notes' => 'Aplica por defecto cuando se supera el umbral de 600.000 € de cifra de negocios o cuando se renuncia voluntariamente a EDS.',
            ],
            'model_quarterly' => '130',
            'model_annual' => '100',
            'valid_from' => '2007-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764',
            'editorial_md' => "## Estimación Directa Normal\n\nEs el régimen **general** para autónomos y profesionales en IRPF. Calculas el rendimiento neto restando a tus ingresos los gastos deducibles efectivamente justificados.\n\n**¿Cuándo me aplica?**\n- Cuando tu cifra de negocios del año anterior supera los 600.000 €.\n- Cuando renuncias a Estimación Directa Simplificada o a Estimación Objetiva.\n\n**¿Qué obligaciones tengo?**\n- Llevar **contabilidad ajustada al Código de Comercio** (libro diario, inventarios, cuentas anuales).\n- Presentar **modelo 130** trimestral (pago fraccionado) y **modelo 100** anual (declaración de la renta).\n\n**¿Por qué importa?** No tienes el límite de 5 % de gastos genéricos que sí tiene EDS, pero a cambio asumes una contabilidad mercantil completa.",
        ];

        yield [
            'code' => 'EDS',
            'scope' => 'irpf',
            'name' => 'Estimación Directa Simplificada (IRPF)',
            'description' => 'Régimen para autónomos con cifra de negocios ≤ 600.000 €/año. Permite deducir un 5 % adicional por gastos genéricos (máx. 2.000 €).',
            'requirements' => [
                'turnover_threshold_max' => 600000,
                'turnover_threshold_min' => null,
                'incompatibilities' => ['EDN', 'EO'],
                'generic_expenses_pct' => 5,
                'generic_expenses_cap_eur' => 2000,
                'notes' => 'Aplicable salvo renuncia. Si se renuncia, mínimo 3 años en EDN.',
            ],
            'model_quarterly' => '130',
            'model_annual' => '100',
            'valid_from' => '2007-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820',
            'editorial_md' => "## Estimación Directa Simplificada\n\nEs el régimen **más habitual** entre autónomos y profesionales liberales. Como en EDN, calculas el rendimiento neto restando ingresos − gastos deducibles, pero con dos diferencias clave:\n\n**Ventajas frente a EDN:**\n- Puedes deducir un **5 % adicional** sobre el rendimiento neto positivo en concepto de \"gastos de difícil justificación\" (con un tope de 2.000 € anuales).\n- No estás obligado a llevar contabilidad mercantil completa: basta con libros registro de ingresos, gastos, bienes de inversión y provisiones.\n\n**¿Cuándo me aplica?**\n- Cifra de negocios del año anterior **≤ 600.000 €**.\n- Que no hayas renunciado expresamente.\n\n**Obligaciones de presentación:** modelo 130 trimestral + modelo 100 anual.\n\n**Renuncia:** se hace en el modelo 036/037 en diciembre y vincula durante 3 años mínimo.",
        ];

        yield [
            'code' => 'EO',
            'scope' => 'irpf',
            'name' => 'Estimación Objetiva (módulos)',
            'description' => 'Régimen de módulos para actividades enumeradas en la Orden anual. Compatible con IVA simplificado o recargo de equivalencia.',
            'requirements' => [
                'turnover_threshold_max_general' => 250000,
                'turnover_threshold_max_agriculture' => 250000,
                'purchases_threshold_max' => 250000,
                'incompatibilities' => ['EDN', 'EDS'],
                'requires_iva_simplificado_or_re' => true,
                'notes' => 'Sólo para actividades incluidas en la Orden Ministerial anual de módulos. Excluyente con cualquier modalidad de estimación directa para todas las actividades del contribuyente.',
            ],
            'model_quarterly' => '131',
            'model_annual' => '100',
            'valid_from' => '2007-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820',
            'editorial_md' => "## Estimación Objetiva (módulos)\n\nEn lugar de calcular el rendimiento real (ingresos − gastos), Hacienda fija un rendimiento por **módulos** según parámetros objetivos: número de empleados, metros del local, kW de potencia eléctrica, vehículos, etc.\n\n**¿Cuándo me aplica?**\n- Tu actividad está en la **Orden Ministerial anual** de módulos (taxis, peluquerías, bares, transporte de mercancías, etc.).\n- No superas los umbrales de exclusión: **250.000 €** de ingresos/compras anuales.\n- No has renunciado expresamente.\n\n**Compatibilidad con IVA:** debe ir con **IVA Simplificado** o con **Recargo de Equivalencia** (en comercio minorista). No es compatible con IVA general en la misma actividad.\n\n**Modelo a presentar:** modelo 131 trimestral (no 130) + modelo 100 anual.\n\n**¿Por qué importa?** Si tu rendimiento real es bajo, los módulos pueden penalizarte; si es alto, te benefician. Es un cálculo a hacer cada año.",
        ];

        yield [
            'code' => 'AR',
            'scope' => 'irpf',
            'name' => 'Atribución de Rentas (IRPF)',
            'description' => 'Régimen para entidades en atribución (CB, SC, herencias yacentes): los rendimientos se atribuyen a los socios/comuneros en su IRPF.',
            'requirements' => [
                'entity_types' => ['comunidad de bienes', 'sociedad civil sin objeto mercantil', 'herencia yacente'],
                'incompatibilities' => [],
                'notes' => 'La entidad presenta el modelo 184 informativo y los socios/comuneros declaran su parte en su modelo 100.',
            ],
            'model_quarterly' => null,
            'model_annual' => '184',
            'valid_from' => '2007-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764',
            'editorial_md' => "## Atribución de Rentas\n\nEs un régimen para entidades **sin personalidad jurídica fiscal propia**: comunidades de bienes (CB), sociedades civiles sin objeto mercantil y herencias yacentes.\n\n**Cómo funciona:**\n- La entidad calcula su rendimiento como un autónomo (puede usar EDS, EDN o EO).\n- Ese rendimiento **se reparte entre los socios/comuneros** según el porcentaje pactado.\n- Cada socio integra su parte en **su propio IRPF**.\n\n**Obligaciones:**\n- La entidad: modelo **184** anual (informativo, sin pago).\n- Cada socio: declara su parte en su modelo **100** anual.\n\n**Cuidado:** las sociedades civiles **con objeto mercantil** tributan por **Impuesto de Sociedades** desde 2016, no por atribución de rentas.",
        ];

        yield [
            'code' => 'ASALARIADO_GEN',
            'scope' => 'irpf',
            'name' => 'Asalariado régimen general (IRPF)',
            'description' => 'Trabajadores por cuenta ajena: rendimientos del trabajo con retención por la empresa.',
            'requirements' => [
                'income_type' => 'rendimientos_del_trabajo',
                'incompatibilities' => [],
                'notes' => 'La empresa retiene mensualmente y presenta modelos 111/190. El trabajador presenta modelo 100 si supera el umbral de obligación.',
            ],
            'model_quarterly' => null,
            'model_annual' => '100',
            'valid_from' => '2007-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764',
            'editorial_md' => "## Asalariado — IRPF\n\nSi trabajas **por cuenta ajena**, tu IRPF se retiene mensualmente en nómina por la empresa según tu situación familiar y nivel de retribución.\n\n**¿Tengo que presentar declaración?**\n- **Sí siempre** si tienes ingresos > 22.000 € de un único pagador, o > 15.876 € si tienes dos pagadores y el segundo paga más de 1.500 €/año (cifras 2024-2025).\n- También si tienes otras rentas (alquileres, ganancias patrimoniales, etc.).\n\n**¿Y si no llego al mínimo?**\nNo estás obligado, pero **suele convenir** presentar para recuperar retenciones excesivas, deducciones por hijos, vivienda habitual, etc.\n\n**Modelo:** modelo **100** anual entre abril y junio del ejercicio siguiente.",
        ];

        // ============================================================
        // IVA (11)
        // ============================================================
        yield [
            'code' => 'IVA_GEN',
            'scope' => 'iva',
            'name' => 'IVA Régimen General',
            'description' => 'Régimen por defecto: IVA repercutido − IVA soportado, declaración trimestral o mensual.',
            'requirements' => [
                'incompatibilities_same_activity' => ['IVA_SIMPLE', 'IVA_RE', 'IVA_REAGP'],
                'notes' => 'Aplica por defecto a todos los empresarios y profesionales no acogidos a régimen especial.',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Régimen General\n\nEs el régimen **por defecto** del IVA. Calculas la cuota a ingresar como **IVA repercutido (cobrado) − IVA soportado (pagado)**.\n\n**Tipos impositivos vigentes:**\n- **21 %** general\n- **10 %** reducido (alimentos, transporte de viajeros, hostelería…)\n- **4 %** superreducido (pan, leche, libros, medicamentos, viviendas de protección oficial…)\n- **0 %** o exento (sanidad, educación, alquiler vivienda, exportaciones…)\n\n**Obligaciones:**\n- **Modelo 303** trimestral (o mensual si facturas > 6.010.121,04 €/año).\n- **Modelo 390** declaración-resumen anual.\n- **Libros registro** de facturas emitidas, recibidas, bienes de inversión y operaciones intracomunitarias.\n\n**Devengo:** se devenga al emitir la factura (criterio devengo). Si quieres devengarlo al cobro, mira **IVA Criterio de Caja**.",
        ];

        yield [
            'code' => 'IVA_CAJA',
            'scope' => 'iva',
            'name' => 'IVA Régimen Especial del Criterio de Caja',
            'description' => 'Devengo al cobro/pago efectivo (en lugar de al emitir factura). Cifra de negocios ≤ 2 M€.',
            'requirements' => [
                'turnover_threshold_max' => 2000000,
                'cash_payments_max_per_recipient' => 100000,
                'incompatibilities' => ['IVA_SIMPLE'],
                'notes' => 'Opcional, requiere comunicación expresa en modelo 036 en diciembre. El destinatario también difiere su deducción.',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '2014-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Criterio de Caja\n\nNo declaras el IVA cuando emites la factura, sino **cuando cobras realmente**. Y deduces el IVA soportado **cuando pagas a tus proveedores**.\n\n**¿Cuándo me conviene?**\n- Tienes clientes que pagan tarde y no quieres adelantar el IVA repercutido a Hacienda.\n- Tu cifra de negocios anual no supera **2.000.000 €**.\n- No tienes cobros en efectivo > 100.000 €/año a un mismo cliente.\n\n**Atención:**\n- Si al **31 de diciembre del año siguiente** sigues sin cobrar la factura, **el IVA se devenga obligatoriamente** ese día.\n- Tu cliente también difiere la deducción hasta que te paga (o hasta esa fecha tope).\n\n**Cómo solicitarlo:** marcando la casilla en el modelo 036 en **diciembre** del año previo. Vincula a todas las operaciones del régimen excepto importaciones, AIB, y ciertas operaciones específicas.",
        ];

        yield [
            'code' => 'IVA_SIMPLE',
            'scope' => 'iva',
            'name' => 'IVA Régimen Simplificado',
            'description' => 'Cuota IVA por módulos para actividades acogidas a Estimación Objetiva del IRPF. Modelo 303 trimestral con casillas específicas.',
            'requirements' => [
                'turnover_threshold_max' => 250000,
                'must_be_in_eo_irpf' => true,
                'incompatibilities' => ['IVA_GEN', 'IVA_CAJA'],
                'notes' => 'Va aparejado a EO en IRPF. La cuota se calcula por módulos pero se ingresa el IVA real soportado deducible al final del año.',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Régimen Simplificado\n\nEs el **paralelo del IVA al régimen de Estimación Objetiva (módulos) del IRPF**. Si una actividad tributa por módulos en IRPF, en IVA tributa por simplificado o por recargo de equivalencia.\n\n**Cómo funciona:**\n- Pagas trimestralmente una **cuota IVA fijada por módulos** (índices y módulos publicados en la Orden Ministerial anual).\n- Al final del año regularizas con el **IVA soportado realmente deducible**.\n\n**Requisitos:**\n- Estar en **EO en IRPF** para esa misma actividad.\n- Cifra de negocios ≤ **250.000 €** anuales.\n- Adquisiciones (excluido inmovilizado) ≤ 250.000 € anuales.\n\n**Modelo:** modelo 303 trimestral con casillas específicas + 390 anual.\n\n**Renuncia:** vincula al menos 3 años. Renunciar a uno (EO IRPF o Simplificado IVA) implica renunciar al otro.",
        ];

        yield [
            'code' => 'IVA_RE',
            'scope' => 'iva',
            'name' => 'IVA Recargo de Equivalencia',
            'description' => 'Régimen obligatorio para comerciantes minoristas personas físicas: el proveedor repercute IVA + recargo y el minorista no presenta IVA.',
            'requirements' => [
                'entity_type_required' => 'persona_fisica',
                'activity_type_required' => 'comercio_minorista',
                'sales_to_individuals_pct_min' => 80,
                'incompatibilities' => ['IVA_GEN', 'IVA_SIMPLE'],
                'notes' => 'Obligatorio cuando se cumplen los requisitos. El proveedor añade en factura: 5,2 % al 21 %, 1,4 % al 10 %, 0,5 % al 4 %, 1,75 % al tabaco.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Recargo de Equivalencia\n\nRégimen **obligatorio** (no opcional) para **comerciantes minoristas personas físicas o entidades en atribución de rentas** que vendan al consumidor final más del 80 % de su facturación.\n\n**Cómo funciona:**\n- Tus proveedores te facturan el IVA **+ un recargo adicional**:\n  - **5,2 %** sobre artículos al 21 %\n  - **1,4 %** sobre artículos al 10 %\n  - **0,5 %** sobre artículos al 4 %\n  - **1,75 %** sobre tabaco\n- Tú no presentas modelos de IVA. No deduces IVA soportado. No haces declaración periódica.\n- Repercutes el 21/10/4 % normal a tus clientes.\n\n**Ventaja:** simplificación administrativa total — el comercio minorista no toca un solo modelo de IVA.\n\n**Inconveniente:** no recuperas IVA de inversiones, gastos generales, etc.\n\n**Excluye:** ventas mayoristas, joyería, peletería, vehículos a motor, embarcaciones, objetos de arte, antigüedades, maquinaria industrial, materiales de construcción.",
        ];

        yield [
            'code' => 'IVA_REAGP',
            'scope' => 'iva',
            'name' => 'IVA Régimen Especial Agricultura, Ganadería y Pesca',
            'description' => 'Régimen para titulares de explotaciones agrícolas, ganaderas, forestales o pesqueras: no facturan IVA y reciben compensación a tanto alzado.',
            'requirements' => [
                'turnover_threshold_max' => 250000,
                'activity_type_required' => 'agraria_ganadera_forestal_pesquera',
                'compensation_pct_agro' => 12,
                'compensation_pct_livestock_fishing' => 10.5,
                'incompatibilities' => ['IVA_GEN'],
                'notes' => 'El destinatario empresario abona la compensación al titular y la deduce en su IVA.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Régimen Especial Agricultura, Ganadería y Pesca (REAGP)\n\nRégimen específico para titulares de **explotaciones agrícolas, ganaderas, forestales o pesqueras** que cumplan los requisitos.\n\n**Cómo funciona:**\n- **No facturas IVA** a tus clientes ni presentas modelos periódicos.\n- Recibes una **compensación a tanto alzado** del comprador empresario (no del consumidor final):\n  - **12 %** sobre productos agrícolas y forestales.\n  - **10,5 %** sobre productos ganaderos y pesqueros.\n- Esa compensación equivale al IVA soportado en tus inputs y la \"recuperas\" así.\n\n**Requisitos:**\n- Volumen de operaciones ≤ **250.000 €**.\n- No realizar transformación industrial relevante de los productos.\n- No haber renunciado.\n\n**Importante:** sólo aplica a entregas a empresarios, exportaciones y servicios accesorios. Las ventas al consumidor final llevan IVA general.",
        ];

        yield [
            'code' => 'IVA_REBU',
            'scope' => 'iva',
            'name' => 'IVA Régimen Especial Bienes Usados',
            'description' => 'Régimen para revendedores de bienes usados, objetos de arte, antigüedades y de colección: IVA sobre el margen de beneficio.',
            'requirements' => [
                'entity_type' => 'revendedor_habitual',
                'goods_categories' => ['bienes_usados', 'objetos_arte', 'antiguedades', 'objetos_coleccion'],
                'notes' => 'Opcional operación a operación. Permite tributar por margen, especialmente útil cuando el bien se compra a particular sin IVA.',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1995-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Régimen Especial Bienes Usados (REBU)\n\nRégimen para **revendedores habituales** de bienes usados, objetos de arte, antigüedades y objetos de colección.\n\n**Cómo funciona:**\n- En lugar de IVA sobre el precio total de venta, lo aplicas **sólo sobre el margen** (precio venta − precio compra).\n- Útil cuando compras a particulares (que no facturan IVA) y revendes con IVA.\n\n**Modalidades:**\n- Determinación operación por operación (general).\n- Determinación global (sólo bienes ≤ 6.000 € unitarios).\n\n**Atención:** quien compra a un revendedor REBU **no puede deducirse el IVA del margen** (al no figurar como cuota repercutida en la factura). Por eso muchos revendedores ofrecen ambas opciones (REBU vs general) según el cliente.\n\n**Modelo:** modelo 303 trimestral + 390 anual, con casillas específicas para REBU.",
        ];

        yield [
            'code' => 'IVA_AAVV',
            'scope' => 'iva',
            'name' => 'IVA Régimen Especial Agencias de Viajes',
            'description' => 'Régimen obligatorio para agencias que prestan en nombre propio servicios usando bienes o servicios de terceros: IVA sobre margen.',
            'requirements' => [
                'activity_type_required' => 'agencia_viajes',
                'must_act_in_own_name' => true,
                'notes' => 'Aplica también a organizadores de circuitos turísticos. No deducible el IVA soportado de las prestaciones que repercute al viajero.',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Régimen Especial Agencias de Viajes\n\nRégimen **obligatorio** para agencias de viajes y organizadores de circuitos turísticos que actúan **en nombre propio** y utilizan bienes o servicios de terceros (hoteles, transporte, etc.).\n\n**Cómo funciona:**\n- Calculas el IVA sobre el **margen bruto** (precio cobrado al viajero − costes de bienes/servicios adquiridos a terceros para el viaje).\n- No puedes deducir el IVA soportado en esas adquisiciones.\n- Tipo aplicable: **21 % sobre el margen**.\n\n**No aplica a:**\n- Servicios prestados en nombre y por cuenta ajena (intermediación pura).\n- Servicios prestados con medios propios (autocares propios, etc.).\n\n**Modelo:** 303 trimestral con casillas específicas + 390 anual.",
        ];

        yield [
            'code' => 'IVA_ISP',
            'scope' => 'iva',
            'name' => 'IVA Inversión del Sujeto Pasivo',
            'description' => 'Operaciones específicas (chatarra, oro de inversión, ejecuciones de obra inmobiliaria, etc.) en que el destinatario es el sujeto pasivo del IVA.',
            'requirements' => [
                'operation_categories' => [
                    'chatarra',
                    'oro_inversion',
                    'ejecuciones_obra_inmobiliaria_b2b',
                    'entregas_inmuebles_renuncia_exencion',
                    'gas_electricidad_no_establecidos',
                    'telefonos_moviles_consolas_ordenadores',
                ],
                'notes' => 'Quien recibe la entrega o servicio autorrepercute el IVA y lo deduce simultáneamente. El proveedor factura sin IVA con la mención "Inversión del sujeto pasivo".',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## Inversión del Sujeto Pasivo (ISP)\n\nMecanismo por el que **el comprador o destinatario** del bien o servicio se convierte en sujeto pasivo del IVA, en lugar del vendedor.\n\n**Cuándo aplica (casos principales):**\n- Entregas de **chatarra** entre empresarios.\n- Entregas de **oro de inversión** (con renuncia a exención).\n- **Ejecuciones de obra inmobiliaria** entre empresarios (promoción y rehabilitación).\n- Entregas de **inmuebles** cuando se renuncia a la exención.\n- Suministros de gas y electricidad por no establecidos.\n- Entregas B2B de teléfonos móviles, consolas, ordenadores y tablets > 10.000 €.\n\n**Cómo se factura:**\n- El proveedor emite factura **sin repercutir IVA** con la mención literal \"Inversión del sujeto pasivo\" (Art. 84.Uno.2 LIVA).\n- El destinatario **autorrepercute** el IVA en su modelo 303 (lo declara como repercutido) y simultáneamente **lo deduce** (si tiene derecho).\n\n**Efecto neto:** habitualmente neutro fiscalmente, pero indispensable hacerlo correctamente — Hacienda revisa con frecuencia.",
        ];

        yield [
            'code' => 'IVA_OIC',
            'scope' => 'iva',
            'name' => 'IVA Operaciones Intracomunitarias',
            'description' => 'Adquisiciones intracomunitarias de bienes y servicios entre empresarios identificados con NIF-IVA en Estados miembros distintos.',
            'requirements' => [
                'requires_vat_id' => true,
                'requires_vies_registration' => true,
                'notes' => 'Las entregas intracomunitarias B2B están exentas en origen (IVA repercutido en destino vía ISP). Requiere darse de alta en el ROI (modelo 036).',
            ],
            'model_quarterly' => '303',
            'model_annual' => '390',
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Operaciones Intracomunitarias\n\nMecanismo armonizado en la UE para evitar doble tributación cuando hay operaciones B2B entre empresarios de distintos Estados miembros.\n\n**Cómo funciona en venta (entrega intracomunitaria):**\n- Tú facturas **sin IVA** español a un empresario UE con NIF-IVA válido (verificable en el censo VIES).\n- Es una **entrega exenta** en España.\n- Declaras la operación en el modelo **303** (casillas específicas) y en el modelo **349** informativo recapitulativo.\n\n**Cómo funciona en compra (adquisición intracomunitaria):**\n- Recibes la factura sin IVA.\n- Tú **autorrepercutes** el IVA español como AIB y simultáneamente lo deduces (si procede).\n- También figura en modelo 349.\n\n**Requisito previo:** estar dado de alta en el **Registro de Operadores Intracomunitarios (ROI)** mediante modelo 036 marcando la casilla 582. La AEAT verifica antes de asignar NIF-IVA.\n\n**Comprueba siempre el VIES** del cliente antes de facturar sin IVA: si su NIF no es válido, debes repercutir IVA.",
        ];

        yield [
            'code' => 'IVA_OSS',
            'scope' => 'iva',
            'name' => 'IVA OSS (Ventanilla Única) — Régimen de Servicios y Bienes a Distancia',
            'description' => 'Ventanilla única de la UE para declarar el IVA de ventas a distancia B2C en otros EM por encima del umbral de 10.000 €.',
            'requirements' => [
                'b2c_threshold_total_ue' => 10000,
                'incompatibilities' => [],
                'notes' => 'Permite ingresar el IVA de todos los Estados miembros desde España vía modelo 369. Aplica también IOSS para importaciones ≤ 150 €.',
            ],
            'model_quarterly' => '369',
            'model_annual' => null,
            'valid_from' => '2021-07-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA OSS (One Stop Shop)\n\n**Ventanilla Única europea** para vendedores online y prestadores de servicios B2C que operan en varios Estados miembros.\n\n**¿Cuándo me aplica?**\n- Vendes online a consumidores finales (B2C) en **otros Estados miembros** (no en España).\n- Tu volumen total de estas ventas a distancia + servicios TBE supera **10.000 € anuales** (umbral comunitario unificado desde 1-7-2021).\n\n**Cómo funciona:**\n- En lugar de darte de alta como sujeto pasivo en cada país, **te registras una sola vez en España** (modelo 035).\n- Aplicas el **tipo de IVA del país del consumidor** en cada venta.\n- Presentas **modelo 369 trimestral** declarando el IVA de cada país.\n- La AEAT lo distribuye a las administraciones tributarias correspondientes.\n\n**Variantes:**\n- **OSS Unión** — para ventas a distancia y servicios B2C en la UE.\n- **OSS no Unión** — para empresas no UE que prestan servicios B2C a consumidores UE.\n- **IOSS** — para importaciones de bienes ≤ 150 €.\n\n**Si no superas 10.000 €**, puedes seguir aplicando IVA español a tus ventas B2C UE.",
        ];

        yield [
            'code' => 'IVA_EXENTO',
            'scope' => 'iva',
            'name' => 'IVA Operaciones Exentas',
            'description' => 'Actividades exentas por LIVA: sanidad, educación, alquiler vivienda, financieras, seguros, etc. No repercuten IVA y no deducen IVA soportado.',
            'requirements' => [
                'exempt_categories' => [
                    'sanitarias',
                    'educacion_reglada',
                    'culturales',
                    'asistencia_social',
                    'arrendamiento_vivienda',
                    'operaciones_financieras',
                    'seguros',
                    'loterias_juegos',
                ],
                'notes' => 'Los exentos plenos (exportaciones, AIB) sí permiten deducir IVA soportado. Los exentos del Art. 20 LIVA son exenciones limitadas (sin derecho a deducción).',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '1993-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'editorial_md' => "## IVA Operaciones Exentas\n\nLa Ley del IVA exime determinadas operaciones, principalmente por motivos de interés general. **No repercutes IVA** a tus clientes, pero tampoco puedes deducir el IVA soportado de tus compras (en el caso de exenciones limitadas).\n\n**Exenciones limitadas (Art. 20 LIVA):**\n- **Sanitarias** (médicos, hospitales, dentistas, psicólogos colegiados…).\n- **Educativas** regladas (academias homologadas, idiomas para uso profesional…).\n- **Culturales** (representaciones, conciertos por entidades culturales…).\n- **Asistencia social** (centros de día, ayuda a domicilio…).\n- **Arrendamiento de vivienda** (sin servicios complementarios).\n- **Operaciones financieras** (banca, créditos, garantías…).\n- **Seguros y reaseguros**.\n- **Loterías y juegos de azar**.\n\n**Importante:**\n- Si tu actividad principal es exenta, normalmente **no presentas modelo 303** ni declaración anual de IVA.\n- Si combinas operaciones exentas y no exentas, aplicas la **regla de prorrata** (compleja).\n- Las **exportaciones** y **entregas intracomunitarias** son exenciones plenas: no repercutes IVA pero sí deduces el soportado.",
        ];

        // ============================================================
        // IS (9)
        // ============================================================
        yield [
            'code' => 'IS_GEN',
            'scope' => 'is',
            'name' => 'Impuesto sobre Sociedades — Régimen General',
            'description' => 'Régimen general del IS. Tipo nominal 25 %.',
            'requirements' => [
                'incompatibilities' => ['IS_ERD', 'IS_MICRO', 'IS_STARTUP'],
                'tax_rate_pct' => 25,
                'notes' => 'Aplica por defecto a sociedades mercantiles que no cumplen requisitos de regímenes especiales.',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2015-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
            'editorial_md' => "## Impuesto sobre Sociedades — Régimen General\n\nLas **sociedades mercantiles** (SL, SA…) tributan en IS sobre el beneficio fiscal del ejercicio.\n\n**Tipo nominal:** **25 %** sobre la base imponible.\n\n**Esquema básico de cálculo:**\n1. Resultado contable del ejercicio.\n2. (±) Ajustes fiscales por diferencias permanentes y temporarias.\n3. (−) Bases imponibles negativas de ejercicios anteriores (limitaciones según facturación).\n4. **Base imponible** × 25 % = cuota íntegra.\n5. (−) Deducciones (I+D+i, doble imposición, donaciones, etc.).\n6. (−) Retenciones, pagos fraccionados.\n7. **Cuota a ingresar/devolver**.\n\n**Obligaciones:**\n- **Pagos fraccionados** (modelo 202) en abril, octubre y diciembre.\n- **Declaración anual** (modelo 200) en julio del año siguiente al cierre.\n- **Cuentas anuales** depositadas en el Registro Mercantil.\n\n**Atención al modo de cálculo de pagos fraccionados:**\n- Por defecto, 18 % de la cuota del último IS.\n- Opcional (obligatorio si facturación > 6 M€): % sobre la base del periodo en curso.",
        ];

        yield [
            'code' => 'IS_ERD',
            'scope' => 'is',
            'name' => 'IS — Empresa de Reducida Dimensión (ERD)',
            'description' => 'Régimen para sociedades con cifra de negocios < 10 M€. Permite libertad de amortización con creación de empleo y otras ventajas.',
            'requirements' => [
                'turnover_threshold_max' => 10000000,
                'incompatibilities' => ['IS_MICRO', 'IS_GEN_OBLIGATORIO'],
                'tax_rate_pct' => 25,
                'notes' => 'Régimen especial fiscalmente más favorable. Permite mantener tipo 25 %, libertad de amortización, dotación contable de pérdidas por insolvencia, reservas de nivelación, etc.',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2015-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
            'editorial_md' => "## IS — Empresa de Reducida Dimensión\n\nRégimen especial **automático** para sociedades cuya cifra de negocios del periodo anterior haya sido **< 10 millones €**.\n\n**Beneficios fiscales principales:**\n- **Libertad de amortización con creación de empleo**: amortizas inversiones en inmovilizado material/inversiones inmobiliarias en proporción al incremento de plantilla, hasta 120.000 € por empleo creado/mantenido.\n- **Amortización acelerada** (× 2) sobre tablas oficiales para resto de inversiones.\n- **Pérdidas por insolvencia** dotadas globalmente al 1 % del saldo de deudores al cierre.\n- **Reserva de nivelación**: difiere el 10 % de la base imponible (max 1 M€) compensable en 5 años con BIN futuras.\n- Tipo IS: **25 %** (mismo que régimen general; ya no hay tipo reducido aplicable).\n\n**No tienes que solicitarlo**: aplica por imperio de la ley si cumples el umbral. Si en el ejercicio **siguiente** sobrepasas los 10 M€, mantienes los beneficios durante 3 ejercicios adicionales.",
        ];

        yield [
            'code' => 'IS_MICRO',
            'scope' => 'is',
            'name' => 'IS — Microempresa',
            'description' => 'Régimen progresivo para microempresas (cifra de negocios < 1 M€): 21 % hasta 50.000 € y 25 % en el exceso (a partir de 2025).',
            'requirements' => [
                'turnover_threshold_max' => 1000000,
                'tax_rate_pct_first_bracket' => 21,
                'tax_rate_pct_first_bracket_threshold' => 50000,
                'tax_rate_pct_excess' => 25,
                'incompatibilities' => ['IS_GEN', 'IS_ERD'],
                'notes' => 'Introducido por Ley 7/2024 con calendario progresivo: 24/22 (2025), 23/21 (2026), 22/20 (2027), 21/19 (2028), 20/19 (2029) según ejercicio.',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2025-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2024-26756',
            'editorial_md' => "## IS — Microempresa\n\nRégimen específico para microempresas (cifra de negocios del periodo anterior **< 1.000.000 €**) introducido por la Ley 7/2024 (BOE 21-12-2024) con un **calendario progresivo de tipos reducidos**.\n\n**Tipos aplicables (calendario):**\n| Ejercicio | Primer 50.000 € | Resto |\n|---|---|---|\n| 2025 | 24 % | 22 % |\n| 2026 | 23 % | 21 % |\n| 2027 | 22 % | 20 % |\n| 2028 | 21 % | 19 % |\n| 2029+ | 20 % | 19 % |\n\n**Diseño del beneficio:**\n- Pretende reducir la presión fiscal de las empresas más pequeñas para favorecer su crecimiento.\n- Es **automático**: aplicas el tipo reducido si cumples el umbral, sin solicitud.\n\n**Compatible con ERD** (siempre que cumpla los requisitos de ambos): se aplica el más favorable.",
        ];

        yield [
            'code' => 'IS_STARTUP',
            'scope' => 'is',
            'name' => 'IS — Empresa Emergente (Startup)',
            'description' => 'Régimen para empresas calificadas como emergentes según Ley 28/2022: tipo 15 % los 4 primeros ejercicios con beneficios.',
            'requirements' => [
                'tax_rate_pct' => 15,
                'duration_years' => 4,
                'must_be_certified_by_enisa' => true,
                'incompatibilities' => ['IS_GEN', 'IS_MICRO'],
                'notes' => 'Calificación oficial por ENISA. La sociedad debe ser de nueva creación o haber transcurrido < 5 años (7 si biotech, energía o industrial).',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2023-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-21739',
            'editorial_md' => "## IS — Empresa Emergente (Startup)\n\nRégimen especial introducido por la **Ley 28/2022 de fomento del ecosistema de empresas emergentes** (Ley de Startups).\n\n**Requisitos para ser calificada como emergente:**\n- Calificación oficial por **ENISA** (registro nacional).\n- Antigüedad **< 5 años** (7 años para biotecnología, energía o industrial estratégico).\n- No haber distribuido dividendos.\n- No cotizar en mercados regulados.\n- Sede o establecimiento permanente en España.\n- **Plantilla mínima**: al menos 60 % en territorio español.\n- Carácter **innovador** acreditado por ENISA.\n\n**Beneficios fiscales en IS:**\n- **Tipo del 15 %** (en vez del 25 %) durante los **4 primeros ejercicios** con base imponible positiva.\n- **Aplazamiento sin garantía** del IS de los 2 primeros ejercicios (12 y 6 meses respectivamente).\n- **No obligación** de pagos fraccionados durante los 2 primeros ejercicios.\n\n**Beneficios para inversores y empleados:**\n- Deducción IRPF por inversión: **50 %** sobre 100.000 € máximo.\n- Stock options: exención hasta 50.000 € anuales y diferimiento del resto.",
        ];

        yield [
            'code' => 'IS_COOP',
            'scope' => 'is',
            'name' => 'IS — Cooperativas (protegidas y especialmente protegidas)',
            'description' => 'Régimen fiscal de cooperativas (Ley 20/1990): tipos reducidos sobre resultados cooperativos (20 %) y bonificaciones específicas.',
            'requirements' => [
                'tax_rate_pct_cooperative_results' => 20,
                'tax_rate_pct_extra_cooperative_results' => 25,
                'special_protection_bonus' => 50,
                'notes' => 'Las protegidas tributan al 20 % los resultados cooperativos. Las especialmente protegidas (agrarias, trabajo asociado, mar, consumo…) tienen además bonificación del 50 % en cuota.',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '1990-12-19',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1990-30735',
            'editorial_md' => "## IS — Cooperativas\n\nLas cooperativas tienen un **régimen fiscal específico** regulado en la **Ley 20/1990** (anterior incluso a la actual LIS), por su naturaleza social y democrática.\n\n**Categorías:**\n- **Cooperativas no protegidas** — tributan al régimen general.\n- **Cooperativas protegidas** — cumplen requisitos del Art. 6 Ley 20/1990.\n- **Cooperativas especialmente protegidas** — agrarias, de trabajo asociado, del mar, de consumo, de explotación comunitaria, etc.\n\n**Tipos impositivos:**\n- **Resultados cooperativos**: 20 % (los derivados de operaciones con socios).\n- **Resultados extracooperativos**: 25 % (operaciones con terceros, plusvalías…).\n\n**Beneficios adicionales para especialmente protegidas:**\n- **Bonificación del 50 %** en la cuota líquida.\n- Bonificaciones adicionales en función del tipo (agrarias 80 % primeros 5 años en explotaciones comunitarias…).\n\n**Obligaciones específicas:**\n- Dotación obligatoria al **Fondo de Reserva Obligatorio** (mínimo 20 %) y al **Fondo de Educación y Promoción** (mínimo 10 %) — son fiscalmente deducibles.",
        ];

        yield [
            'code' => 'IS_SOCIMI',
            'scope' => 'is',
            'name' => 'IS — SOCIMI (Sociedades Cotizadas Anónimas de Inversión Inmobiliaria)',
            'description' => 'Régimen fiscal especial para SOCIMI: tipo 0 % en IS pero gravamen del 19 % sobre dividendos no distribuidos a socios con < 5 % participación.',
            'requirements' => [
                'tax_rate_pct' => 0,
                'special_levy_pct' => 19,
                'min_capital_eur' => 5000000,
                'min_distributable_dividend_pct' => 80,
                'min_listed_assets_pct' => 80,
                'must_be_listed' => true,
                'notes' => 'Régimen específico Ley 11/2009. SOCIMI debe cotizar en mercado regulado o sistema multilateral. Mantenimiento mínimo 3 años de inmuebles.',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2009-10-26',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2009-17000',
            'editorial_md' => "## IS — SOCIMI\n\n**Sociedades Cotizadas Anónimas de Inversión Inmobiliaria** — vehículo de inversión inmobiliaria con régimen fiscal específico, regulado por **Ley 11/2009**, equivalente a las REIT internacionales.\n\n**Tributación en IS:**\n- **Tipo 0 %** sobre los rendimientos derivados del arrendamiento o transmisión de bienes inmuebles afectos.\n- Existe un **gravamen especial del 19 %** sobre dividendos no distribuidos cuando el socio perceptor (con participación < 5 %) está sujeto a un tipo inferior al 10 % en su tributación de los dividendos.\n\n**Requisitos principales:**\n- **Capital mínimo**: 5 millones €.\n- Cotización en **mercado regulado** o sistema multilateral de negociación.\n- **80 % del activo** invertido en inmuebles urbanos arrendados, terrenos para promoción de inmuebles para alquiler, o participaciones en otras SOCIMI.\n- **80 % de las rentas** procedentes del alquiler.\n- **Distribución obligatoria de dividendos** del 80 % de rentas del alquiler y 50 % de plusvalías por venta.\n- **Mantenimiento mínimo de 3 años** de los inmuebles (7 si son nuevos).",
        ];

        yield [
            'code' => 'IS_SICAV',
            'scope' => 'is',
            'name' => 'IS — SICAV (Sociedades de Inversión de Capital Variable)',
            'description' => 'Vehículo de inversión colectiva: tipo 1 % en IS si cumple requisitos sustantivos (mínimo 100 inversores con tenencia ≥ 2.500 € desde 2022).',
            'requirements' => [
                'tax_rate_pct' => 1,
                'min_investors' => 100,
                'min_investment_per_investor_eur' => 2500,
                'must_be_collective_investment_vehicle' => true,
                'notes' => 'Ley 27/2014 LIS Art. 29. Desde 2022 se endurecen requisitos: cómputo individual del mínimo de inversores con inversión mínima de 2.500 € (5.000 € en SICAV por compartimentos).',
            ],
            'model_quarterly' => '202',
            'model_annual' => '200',
            'valid_from' => '2022-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
            'editorial_md' => "## IS — SICAV\n\nLas **Sociedades de Inversión de Capital Variable** son instituciones de inversión colectiva (IIC) reguladas por la Ley 35/2003 con régimen fiscal especial en IS.\n\n**Tributación:**\n- **Tipo del 1 %** sobre la base imponible si cumplen los requisitos sustantivos.\n- Sus partícipes tributan únicamente al **rescatar/transmitir** las participaciones (diferimiento fiscal).\n\n**Requisitos endurecidos desde 2022 (Ley 11/2021):**\n- **Mínimo 100 partícipes**, computados individualmente.\n- Cada partícipe debe haber realizado una **inversión mínima de 2.500 €** (5.000 € en SICAV por compartimentos).\n- Periodo de adaptación con régimen transitorio.\n\n**Si pierde los requisitos:** tributa al **régimen general (25 %)** y los socios pueden liquidar con un **diferimiento de 6 meses sin coste fiscal**.\n\n**Supervisión:** CNMV.",
        ];

        yield [
            'code' => 'IS_CONSOL',
            'scope' => 'is',
            'name' => 'IS — Consolidación Fiscal',
            'description' => 'Régimen para grupos de sociedades: declaran como sujeto único compensando bases imponibles entre sociedades del grupo.',
            'requirements' => [
                'min_participation_pct' => 75,
                'min_voting_rights_pct' => 75,
                'incompatibilities' => [],
                'must_be_communicated' => true,
                'notes' => 'Sociedad dominante con ≥ 75 % participación (≥ 70 % para cotizadas). Comunicación a Hacienda en mes anterior al inicio del ejercicio. La dominante presenta el modelo 220.',
            ],
            'model_quarterly' => '222',
            'model_annual' => '220',
            'valid_from' => '2015-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2014-12328',
            'editorial_md' => "## IS — Consolidación Fiscal\n\nRégimen de **tributación grupal** para grupos de sociedades, en el que el grupo tributa como **sujeto pasivo único**.\n\n**Requisitos:**\n- Sociedad **dominante** (residente en España) con participación ≥ **75 %** del capital social y de los derechos de voto en las dependientes (≥ 70 % si las dominadas cotizan).\n- Mantenimiento ininterrumpido durante todo el ejercicio.\n- **Comunicación previa** a la AEAT en el mes anterior al inicio del ejercicio.\n\n**Ventajas:**\n- **Compensación inmediata** de bases imponibles negativas de unas sociedades con positivas de otras.\n- **Eliminación de plusvalías intragrupo** (operaciones internas no tributan).\n- Centralización de retenciones, pagos fraccionados, deducciones.\n\n**Modelos específicos:**\n- **Modelo 220** anual presentado por la dominante.\n- **Modelo 222** pagos fraccionados consolidados.\n- Cada dependiente sigue presentando su 200 individual a efectos informativos (con cuota cero).\n\n**Limitaciones:** límite del 50 % de la cuota íntegra para deducciones, límites a la deducibilidad de gastos financieros, etc.",
        ];

        yield [
            'code' => 'IS_ESFL',
            'scope' => 'is',
            'name' => 'IS — Entidades sin Fines Lucrativos (Ley 49/2002)',
            'description' => 'Régimen fiscal de mecenazgo: ESFL acogidas a Ley 49/2002 tributan al 10 % las explotaciones económicas no exentas.',
            'requirements' => [
                'tax_rate_pct' => 10,
                'entity_types' => ['fundacion', 'asociacion_utilidad_publica', 'ong_desarrollo', 'federaciones_deportivas', 'iglesia_catolica_otros'],
                'must_be_registered_with_acuerdo' => true,
                'notes' => 'Las rentas de explotaciones económicas exentas tributan a 0 %. Sólo las no exentas tributan al 10 %. Aporta deducciones a donantes (modelo 182).',
            ],
            'model_quarterly' => null,
            'model_annual' => '200',
            'valid_from' => '2002-12-25',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2002-25039',
            'editorial_md' => "## IS — Entidades sin Fines Lucrativos\n\nRégimen fiscal especial para **fundaciones, asociaciones declaradas de utilidad pública, ONG de desarrollo, federaciones deportivas y otras entidades sin fines lucrativos** que se acojan a la **Ley 49/2002 de mecenazgo**.\n\n**Tributación:**\n- **Rentas de actividades económicas exentas** (educación, sanidad, cultura, asistencia social, etc.): **0 %**.\n- **Rentas de actividades económicas no exentas**: **10 %**.\n- **Rentas patrimoniales**: exentas.\n\n**Requisitos para acogerse:**\n- Inscripción registral.\n- Que se persigan **fines de interés general** (asistenciales, culturales, científicos, deportivos, etc.).\n- Que destinen al menos el **70 % de los ingresos** a esos fines.\n- Que los cargos directivos sean **gratuitos** (con excepciones limitadas).\n- Comunicación a la AEAT optando por el régimen.\n\n**Otras ventajas:**\n- Exenciones en IBI, IAE, ITP/AJD para inmuebles afectos.\n- Los **donantes** se deducen un % en IRPF/IS (modelo 182 que la entidad presenta).\n\n**Si no se acoge a la Ley 49/2002**, las ESFL tributan por régimen parcial del IS (Art. 9.3 LIS) — menos ventajoso.",
        ];

        // ============================================================
        // SS — Seguridad Social (6)
        // ============================================================
        yield [
            'code' => 'RG',
            'scope' => 'ss',
            'name' => 'Régimen General de la Seguridad Social',
            'description' => 'Régimen para trabajadores por cuenta ajena: tipos de cotización empresa y trabajador sobre la base de cotización.',
            'requirements' => [
                'employee_type_required' => 'cuenta_ajena',
                'incompatibilities' => ['RETA'],
                'notes' => 'Liquidación mensual mediante Sistema de Liquidación Directa (SLD) — modelo TC1/TC2 obsoleto desde 2015.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '2015-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11724',
            'editorial_md' => "## Régimen General de la Seguridad Social\n\nRégimen aplicable a **trabajadores por cuenta ajena** ordinarios.\n\n**Tipos de cotización 2025 (orientativos, sujetos a Orden anual):**\n- **Contingencias comunes**: 28,30 % (23,60 % empresa + 4,70 % trabajador).\n- **Desempleo**: 6,70 % indefinido / 7,05 % temporal (5,50/6,70 empresa + 1,55/1,60 trabajador).\n- **FOGASA**: 0,20 % (sólo empresa).\n- **Formación profesional**: 0,70 % (0,60 empresa + 0,10 trabajador).\n- **Mecanismo de Equidad Intergeneracional (MEI)**: 0,80 % (0,67 empresa + 0,13 trabajador) en 2025.\n- **Accidentes y enfermedades profesionales (AT/EP)**: tarifa de la Disp. Adic. 4ª Ley 42/2006 (varía por epígrafe CNAE — desde 0,99 % hasta 7,15 %).\n\n**Bases:**\n- Mínima 2025: **1.323 €/mes** (12 pagas) en grupo 1 — varía por categoría.\n- Máxima 2025: **4.909,50 €/mes** (12 pagas) — tope unificado para todos los grupos.\n\n**Liquidación:** mensual mediante el **Sistema de Liquidación Directa (SLD)** de la TGSS, dentro del mes siguiente al devengo.",
        ];

        yield [
            'code' => 'RETA',
            'scope' => 'ss',
            'name' => 'Régimen Especial de Trabajadores Autónomos (RETA)',
            'description' => 'Régimen para autónomos: cuota mensual basada en rendimientos netos previstos (sistema de tramos desde 2023).',
            'requirements' => [
                'employee_type_required' => 'autonomo',
                'incompatibilities' => ['RG_principal'],
                'tramos_system_since' => '2023-01-01',
                'notes' => 'Sistema de cotización por ingresos reales con regularización anual con la AEAT. Disp. Trans. RD 504/2022.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '2023-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-12482',
            'editorial_md' => "## Régimen Especial de Trabajadores Autónomos (RETA)\n\nRégimen aplicable a **trabajadores por cuenta propia** (autónomos), reformado por el **RD-Ley 13/2022** y **RD 504/2022** que introdujeron el **sistema de cotización por ingresos reales** con vigencia desde el 1 de enero de 2023.\n\n**Cómo funciona el sistema de tramos:**\n- Cada año comunicas a la TGSS tu **rendimiento neto previsto**.\n- En función del tramo aplicable, se determina tu **base de cotización mensual** (entre la mínima y la máxima del tramo).\n- Pagas mensualmente la **cuota** correspondiente.\n- Después de la **declaración de IRPF**, la AEAT comunica los **rendimientos reales** y se **regulariza** lo cotizado de menos o de más (sin recargos ni intereses si responde a tu autoliquidación).\n\n**Tramos 2025 (15 escalones):**\n- Tramo más bajo (≤ 670 €/mes rendimiento): cuota **200 €/mes**.\n- Tramos intermedios: cuotas progresivas.\n- Tramo más alto (> 6.000 €/mes): cuota **590 €/mes**.\n\n**Tarifa plana** para nuevos autónomos: **80 €/mes** los primeros 12 meses (prorrogable 12 más si los rendimientos son < SMI anual).\n\n**Beneficios MEI** (Mecanismo Equidad Intergeneracional): cotización adicional 0,80 % aplicada al RETA.",
        ];

        yield [
            'code' => 'AGRARIO',
            'scope' => 'ss',
            'name' => 'Sistema Especial Agrario por cuenta ajena',
            'description' => 'Trabajadores agrarios eventuales por cuenta ajena: bases reducidas y mecanismos especiales (jornadas reales o cotización mensual).',
            'requirements' => [
                'activity_required' => 'agraria_cuenta_ajena',
                'incompatibilities' => [],
                'notes' => 'Integrado en el Régimen General desde 2012. Con bonificaciones específicas y dos modalidades de cotización (jornadas reales o mensual).',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '2012-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2011-20638',
            'editorial_md' => "## Sistema Especial Agrario\n\nRégimen aplicable a **trabajadores por cuenta ajena del sector agrario** (Sistema Especial integrado en el Régimen General desde 2012, **Ley 28/2011**).\n\n**Modalidades de cotización:**\n- **Por jornadas reales**: cada día trabajado se cotiza individualmente.\n- **Mensual**: para trabajadores fijos, cotización por el mes completo.\n\n**Bases especiales 2025 (orientativas):**\n- Bases inferiores a las del Régimen General ordinario.\n- Distintos importes según grupo de cotización (1 a 11).\n\n**Tipos:**\n- Contingencias comunes: 28,30 % (con bonificaciones específicas).\n- Bonificaciones de la cuota empresarial (hasta 13,86 % en algunos casos).\n\n**Beneficiarios principales:**\n- Trabajadores eventuales del campo (campañas de aceituna, fresa, vendimia…).\n- Trabajadores fijos discontinuos del agro.",
        ];

        yield [
            'code' => 'HOGAR',
            'scope' => 'ss',
            'name' => 'Sistema Especial Empleados de Hogar',
            'description' => 'Empleados de hogar (cuidadoras, limpieza doméstica…). Integrado en el Régimen General con bases por tramos retributivos.',
            'requirements' => [
                'activity_required' => 'empleo_hogar_familiar',
                'incompatibilities' => [],
                'employer_required_to_register' => true,
                'unemployment_coverage_since' => '2022-10-01',
                'notes' => 'Reforma RD-Ley 16/2022: cobertura por desempleo y FOGASA desde 1-10-2022. Tramos según retribución mensual.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '2012-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2022-15940',
            'editorial_md' => "## Sistema Especial Empleados de Hogar\n\nRégimen aplicable a **empleados de hogar familiar** (cuidadoras, limpiadoras, niñeras), integrado en el Régimen General desde 2012.\n\n**Reforma 2022 (RD-Ley 16/2022):**\n- Inclusión de la **prestación por desempleo** y **FOGASA** desde 1-10-2022.\n- Equiparación progresiva de derechos al resto del Régimen General.\n- Restricciones al **despido por desistimiento** del empleador.\n\n**Cotización:**\n- Bases por **tramos según retribución** mensual del empleado (8 tramos en 2025).\n- **Reducción del 20 % en cuota empresarial** y bonificación adicional del 45 % por familias numerosas o si la empleadora ha tenido hijos.\n\n**Quién paga:**\n- Si el empleado trabaja **< 60 horas/mes para un mismo empleador**, puede asumir él mismo la obligación de afiliación, alta, baja y cotización.\n- En caso contrario, lo hace el empleador (familia).\n\n**Sin** cotización por **desempleo previa al 1-10-2022** (cobertura sólo desde esa fecha).",
        ];

        yield [
            'code' => 'MAR',
            'scope' => 'ss',
            'name' => 'Régimen Especial de Trabajadores del Mar',
            'description' => 'Trabajadores del mar (marina mercante, pesca, perlicultura…). Regulado por la Ley 47/2015 con tipos específicos y coeficientes reductores.',
            'requirements' => [
                'activity_required' => 'sector_maritimo_pesquero',
                'incompatibilities' => [],
                'notes' => 'Regulado por Ley 47/2015. Bases con coeficientes reductores según grupo (1º, 2º A/B, 3º). Gestionado por el Instituto Social de la Marina (ISM).',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '2015-10-22',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11724',
            'editorial_md' => "## Régimen Especial de Trabajadores del Mar (REM)\n\nRégimen aplicable a los **trabajadores del sector marítimo y pesquero**, regulado por la **Ley 47/2015**. Lo gestiona el **Instituto Social de la Marina (ISM)**, no la TGSS común.\n\n**Ámbito de aplicación:**\n- Marina mercante.\n- Pesca extractiva (mayor altura, altura, litoral, bajura).\n- Acuicultura marina y litoral.\n- Estibadores portuarios.\n- Perlicultura, marisqueo, extracción de productos del mar.\n\n**Particularidades:**\n- **Coeficientes reductores** sobre bases para grupos de trabajadores expuestos a mayor riesgo:\n  - **Grupo 1º**: por cuenta ajena con coeficiente 1 (sin reducción).\n  - **Grupo 2º A**: cuenta propia embarcación armadora colectiva.\n  - **Grupo 2º B**: cuenta propia embarcación armadora individual.\n  - **Grupo 3º**: pesca menor con artes selectivas.\n- **Bonificación por anticipo de la edad de jubilación** según el grupo y los años de embarque.\n\n**Cuotas:** similares al RG con coeficientes correctores.",
        ];

        yield [
            'code' => 'MINERIA',
            'scope' => 'ss',
            'name' => 'Régimen Especial de la Minería del Carbón',
            'description' => 'Trabajadores por cuenta ajena de empresas dedicadas a la minería del carbón (subterránea o a cielo abierto).',
            'requirements' => [
                'activity_required' => 'mineria_carbon',
                'incompatibilities' => [],
                'notes' => 'Bases tarifadas por categorías profesionales. Coeficientes reductores que permiten anticipar la jubilación. Regulado por RD 1100/2024.',
            ],
            'model_quarterly' => null,
            'model_annual' => null,
            'valid_from' => '1970-01-01',
            'legal_reference_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11724',
            'editorial_md' => "## Régimen Especial de la Minería del Carbón\n\nRégimen específico para los **trabajadores por cuenta ajena de empresas dedicadas a la minería del carbón** (extracción subterránea o a cielo abierto).\n\n**Particularidades:**\n- Bases de cotización **tarifadas por categorías profesionales** (no por salario real, salvo regularización).\n- **Coeficientes reductores de la edad de jubilación** muy ventajosos (hasta -10 años para minería subterránea).\n- Subsidios y prestaciones específicas (silicosis, etc.).\n\n**Aplicación residual:**\n- En España queda muy poca actividad de minería del carbón tras los planes de cierre del sector.\n- El régimen permanece para los trabajadores en activo o en situaciones derivadas (prestaciones reconocidas, viudedades, etc.).\n\n**Marco legal de bases:** Orden anual de cotización a la Seguridad Social (anexo específico).",
        ];
    }
}
