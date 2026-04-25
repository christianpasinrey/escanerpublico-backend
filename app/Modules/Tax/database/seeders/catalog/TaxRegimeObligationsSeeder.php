<?php

namespace Modules\Tax\database\seeders\catalog;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeObligation;

/**
 * Obligaciones formales asociadas a cada régimen tributario.
 *
 * Para cada régimen se siembran los modelos AEAT a presentar con su periodicidad
 * y deadline_rule (regla de plazos legible por humanos y procesable por
 * ObligationDeadline VO en futuras fases).
 *
 * Idempotente: usa updateOrCreate por (regime_id, model_code, periodicity).
 *
 * Plazos generales:
 *  - Trimestral: días 1-20 del mes siguiente al fin del trimestre (4T: 1-30 enero).
 *  - Mensual (gran empresa): primeros 30 días del mes siguiente.
 *  - Anual modelo 100 (IRPF): abril a 30 junio del ejercicio siguiente.
 *  - Anual modelo 200 (IS): 25 días siguientes a los 6 meses tras cierre del ejercicio.
 */
class TaxRegimeObligationsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->obligations() as $row) {
            $regime = TaxRegime::query()->where('code', $row['code'])->first();
            if ($regime === null) {
                continue;
            }

            TaxRegimeObligation::query()->updateOrCreate(
                [
                    'regime_id' => $regime->id,
                    'model_code' => $row['model_code'],
                    'periodicity' => $row['periodicity'],
                ],
                [
                    'deadline_rule' => $row['deadline_rule'],
                    'description' => $row['description'],
                    'electronic_required' => $row['electronic_required'] ?? true,
                    'certificate_required' => $row['certificate_required'] ?? false,
                    'draft_available' => $row['draft_available'] ?? false,
                    'valid_from' => $row['valid_from'] ?? null,
                    'source_url' => $row['source_url'] ?? null,
                ],
            );
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function obligations(): iterable
    {
        // ============================================================
        // IRPF — EDS / EDN
        // ============================================================
        yield [
            'code' => 'EDS',
            'model_code' => '130',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero.',
            'description' => 'Pago fraccionado IRPF. Calcula el 20 % del rendimiento neto del trimestre, descontando retenciones y pagos anteriores.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-130.html',
        ];
        yield [
            'code' => 'EDS',
            'model_code' => '100',
            'periodicity' => 'annual',
            'deadline_rule' => 'Del primer día hábil de abril al 30 de junio del ejercicio siguiente.',
            'description' => 'Declaración anual del IRPF. Permite regularizar pagos fraccionados y aplicar deducciones autonómicas y estatales.',
            'draft_available' => true,
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/Renta.html',
        ];
        yield [
            'code' => 'EDN',
            'model_code' => '130',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero.',
            'description' => 'Pago fraccionado IRPF. EDN aplica la misma fórmula que EDS sin la deducción del 5 % de gastos genéricos.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-130.html',
        ];
        yield [
            'code' => 'EDN',
            'model_code' => '100',
            'periodicity' => 'annual',
            'deadline_rule' => 'Del primer día hábil de abril al 30 de junio del ejercicio siguiente.',
            'description' => 'Declaración anual del IRPF.',
            'draft_available' => true,
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/Renta.html',
        ];

        // ============================================================
        // IRPF — EO (módulos)
        // ============================================================
        yield [
            'code' => 'EO',
            'model_code' => '131',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero.',
            'description' => 'Pago fraccionado IRPF para tributación por módulos. Cantidad calculada según los módulos publicados en la Orden Ministerial anual.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-131.html',
        ];
        yield [
            'code' => 'EO',
            'model_code' => '100',
            'periodicity' => 'annual',
            'deadline_rule' => 'Del primer día hábil de abril al 30 de junio del ejercicio siguiente.',
            'description' => 'Declaración anual del IRPF, regularizando los pagos fraccionados realizados con el modelo 131.',
            'draft_available' => true,
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/Renta.html',
        ];

        // ============================================================
        // IRPF — Atribución de Rentas
        // ============================================================
        yield [
            'code' => 'AR',
            'model_code' => '184',
            'periodicity' => 'annual',
            'deadline_rule' => 'Mes de enero del año siguiente al ejercicio (días 1-31).',
            'description' => 'Declaración informativa de entidades en régimen de atribución de rentas. La entidad declara el rendimiento; los socios lo integran en su modelo 100.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-184.html',
        ];

        // ============================================================
        // IRPF — Asalariado
        // ============================================================
        yield [
            'code' => 'ASALARIADO_GEN',
            'model_code' => '100',
            'periodicity' => 'annual',
            'deadline_rule' => 'Del primer día hábil de abril al 30 de junio del ejercicio siguiente.',
            'description' => 'Declaración anual del IRPF para trabajadores por cuenta ajena. Obligatoria si superas 22.000 € de un único pagador o 15.876 € de varios pagadores (cifras 2024).',
            'draft_available' => true,
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/Renta.html',
        ];

        // ============================================================
        // IVA — Régimen General y Caja
        // ============================================================
        yield [
            'code' => 'IVA_GEN',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA. Diferencia entre IVA repercutido y soportado.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_GEN',
            'model_code' => '390',
            'periodicity' => 'annual',
            'deadline_rule' => 'Días 1-30 de enero del año siguiente.',
            'description' => 'Declaración-resumen anual del IVA. Recopila todas las operaciones del año con desglose por tipo y regularización.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-390.html',
        ];
        yield [
            'code' => 'IVA_CAJA',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA con criterio de caja: se declara cuando se cobra/paga, no al emitir factura.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_CAJA',
            'model_code' => '390',
            'periodicity' => 'annual',
            'deadline_rule' => 'Días 1-30 de enero del año siguiente.',
            'description' => 'Declaración-resumen anual del IVA con criterio de caja.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-390.html',
        ];

        // ============================================================
        // IVA — Simplificado
        // ============================================================
        yield [
            'code' => 'IVA_SIMPLE',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA por régimen simplificado: cuota basada en módulos con regularización en 4T.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_SIMPLE',
            'model_code' => '390',
            'periodicity' => 'annual',
            'deadline_rule' => 'Días 1-30 de enero del año siguiente.',
            'description' => 'Declaración-resumen anual del IVA simplificado.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-390.html',
        ];

        // ============================================================
        // IVA — REBU
        // ============================================================
        yield [
            'code' => 'IVA_REBU',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA con casillas REBU específicas para tributación por margen.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_REBU',
            'model_code' => '390',
            'periodicity' => 'annual',
            'deadline_rule' => 'Días 1-30 de enero del año siguiente.',
            'description' => 'Declaración-resumen anual del IVA REBU.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-390.html',
        ];

        // ============================================================
        // IVA — AAVV
        // ============================================================
        yield [
            'code' => 'IVA_AAVV',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA con casillas específicas de Agencias de Viajes.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_AAVV',
            'model_code' => '390',
            'periodicity' => 'annual',
            'deadline_rule' => 'Días 1-30 de enero del año siguiente.',
            'description' => 'Declaración-resumen anual del IVA Agencias de Viajes.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-390.html',
        ];

        // ============================================================
        // IVA — ISP
        // ============================================================
        yield [
            'code' => 'IVA_ISP',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA con casillas específicas para inversión del sujeto pasivo (autorrepercusión).',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];

        // ============================================================
        // IVA — Operaciones intracomunitarias
        // ============================================================
        yield [
            'code' => 'IVA_OIC',
            'model_code' => '303',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, julio, octubre y 1-30 de enero (4T).',
            'description' => 'Autoliquidación trimestral del IVA con casillas para AIB y entregas intracomunitarias.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-303.html',
        ];
        yield [
            'code' => 'IVA_OIC',
            'model_code' => '349',
            'periodicity' => 'monthly',
            'deadline_rule' => 'Días 1-20 del mes siguiente al periodo.',
            'description' => 'Declaración informativa recapitulativa de operaciones intracomunitarias. Mensual (o trimestral si volumen ≤ 50.000 € y otras condiciones).',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-349.html',
        ];

        // ============================================================
        // IVA — OSS
        // ============================================================
        yield [
            'code' => 'IVA_OSS',
            'model_code' => '369',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-30 del mes siguiente al fin del trimestre (excepto régimen de importación, que es mensual).',
            'description' => 'Autoliquidación de la Ventanilla Única (OSS Unión, OSS no Unión e IOSS). Permite ingresar el IVA repercutido en otros Estados miembros desde España.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-369.html',
        ];

        // ============================================================
        // IS
        // ============================================================
        foreach (['IS_GEN', 'IS_ERD', 'IS_MICRO', 'IS_STARTUP', 'IS_COOP', 'IS_SOCIMI', 'IS_SICAV', 'IS_ESFL'] as $isCode) {
            yield [
                'code' => $isCode,
                'model_code' => '202',
                'periodicity' => 'quarterly',
                'deadline_rule' => 'Días 1-20 de abril, octubre y diciembre (no hay 4T en IS).',
                'description' => 'Pago fraccionado del Impuesto sobre Sociedades. Tres pagos al año en abril, octubre y diciembre.',
                'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-202.html',
                'valid_from' => $isCode === 'IS_MICRO' ? '2025-01-01' : ($isCode === 'IS_STARTUP' ? '2023-01-01' : '2015-01-01'),
            ];
            yield [
                'code' => $isCode,
                'model_code' => '200',
                'periodicity' => 'annual',
                'deadline_rule' => 'Dentro de los 25 días naturales siguientes a los 6 meses posteriores al cierre del ejercicio. Cierre 31-12 → presentación 1-25 julio.',
                'description' => 'Declaración anual del Impuesto sobre Sociedades.',
                'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-200.html',
                'valid_from' => $isCode === 'IS_MICRO' ? '2025-01-01' : ($isCode === 'IS_STARTUP' ? '2023-01-01' : '2015-01-01'),
            ];
        }

        // IS — Consolidación fiscal (modelo 220 + 222)
        yield [
            'code' => 'IS_CONSOL',
            'model_code' => '222',
            'periodicity' => 'quarterly',
            'deadline_rule' => 'Días 1-20 de abril, octubre y diciembre.',
            'description' => 'Pago fraccionado consolidado del IS. Presentado por la sociedad dominante del grupo.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-222.html',
        ];
        yield [
            'code' => 'IS_CONSOL',
            'model_code' => '220',
            'periodicity' => 'annual',
            'deadline_rule' => 'Dentro de los 25 días naturales siguientes a los 6 meses posteriores al cierre del ejercicio del grupo.',
            'description' => 'Declaración anual del IS por grupo consolidado, presentada por la sociedad dominante.',
            'source_url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/modelo-220.html',
        ];
    }
}
