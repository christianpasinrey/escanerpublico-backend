<?php

namespace Modules\Tax\database\seeders\catalog;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxRegime;
use Modules\Tax\Models\TaxRegimeCompatibility;

/**
 * Compatibilidades entre regímenes tributarios.
 *
 * Tipos:
 *  - required: si A está activo, B también debe estarlo (ej: EO IRPF requiere IVA_SIMPLE o IVA_RE).
 *  - exclusive: A y B no pueden coexistir (ej: IVA_GEN ↔ IVA_SIMPLE en misma actividad).
 *  - optional: pueden combinarse pero no es obligatorio (ej: IS_GEN ↔ ASALARIADO_GEN — la sociedad y el socio).
 *
 * Idempotente: usa updateOrCreate por (regime_a_id, regime_b_id).
 */
class TaxRegimeCompatibilitySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->compatibilities() as $row) {
            [$a, $b, $type, $notes] = $row;

            $regimeA = TaxRegime::query()->where('code', $a)->first();
            $regimeB = TaxRegime::query()->where('code', $b)->first();

            if ($regimeA === null || $regimeB === null) {
                continue;
            }

            TaxRegimeCompatibility::query()->updateOrCreate(
                ['regime_a_id' => $regimeA->id, 'regime_b_id' => $regimeB->id],
                ['compatibility' => $type, 'notes' => $notes],
            );
        }
    }

    /**
     * @return iterable<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function compatibilities(): iterable
    {
        // ============================================================
        // EXCLUSIONES — IRPF (no se pueden tener simultáneamente)
        // ============================================================
        yield ['EDN', 'EDS', 'exclusive', 'Estimación Directa Normal y Simplificada son excluyentes — la elección de una excluye a la otra para el conjunto de actividades del contribuyente.'];
        yield ['EDN', 'EO', 'exclusive', 'EDN excluye Estimación Objetiva. La renuncia/exclusión de EO obliga a tributar por EDS o EDN.'];
        yield ['EDS', 'EO', 'exclusive', 'EDS y EO son excluyentes — la elección entre régimen de estimación directa y módulos es total para el contribuyente.'];

        // ============================================================
        // EXCLUSIONES — IVA (no en misma actividad)
        // ============================================================
        yield ['IVA_GEN', 'IVA_SIMPLE', 'exclusive', 'IVA General y Simplificado son excluyentes para la misma actividad.'];
        yield ['IVA_GEN', 'IVA_RE', 'exclusive', 'IVA General y Recargo de Equivalencia son excluyentes para la misma actividad de comercio minorista.'];
        yield ['IVA_GEN', 'IVA_REAGP', 'exclusive', 'IVA General y REAGP son excluyentes para la misma explotación agraria/ganadera/pesquera.'];
        yield ['IVA_CAJA', 'IVA_SIMPLE', 'exclusive', 'Criterio de Caja y Simplificado son excluyentes — no se puede aplicar caja a quien tributa por simplificado.'];

        // ============================================================
        // EXCLUSIONES — SS (no se pueden tener simultáneamente como principal)
        // ============================================================
        yield ['RG', 'RETA', 'exclusive', 'Régimen General y RETA son excluyentes como principal. La pluriactividad sí permite estar en ambos por actividades distintas con normas específicas.'];

        // ============================================================
        // EXCLUSIONES — IS (los regímenes especiales son excluyentes entre sí)
        // ============================================================
        yield ['IS_GEN', 'IS_ERD', 'exclusive', 'Régimen general y ERD son técnicamente excluyentes — ERD aplica si se cumplen los requisitos de cifra de negocios.'];
        yield ['IS_GEN', 'IS_MICRO', 'exclusive', 'Régimen general y Microempresa son excluyentes.'];
        yield ['IS_GEN', 'IS_STARTUP', 'exclusive', 'Régimen general y Empresa Emergente son excluyentes durante los 4 ejercicios de aplicación del 15 %.'];
        yield ['IS_ERD', 'IS_MICRO', 'exclusive', 'ERD y Microempresa pueden ser combinables (la microempresa puede ser ERD), pero la aplicación del beneficio es la del régimen más favorable.'];
        yield ['IS_MICRO', 'IS_STARTUP', 'exclusive', 'Microempresa y Empresa Emergente son excluyentes — Startup aplica el 15 % preferentemente.'];

        // ============================================================
        // REQUERIDAS — EO en IRPF requiere IVA_SIMPLE o IVA_RE
        // ============================================================
        yield ['EO', 'IVA_SIMPLE', 'required', 'EO en IRPF debe acompañarse en IVA por Simplificado (o por Recargo de Equivalencia para minoristas). No es compatible con IVA General en la misma actividad.'];
        yield ['EO', 'IVA_RE', 'required', 'EO en IRPF acompañado de Recargo de Equivalencia es la opción habitual para comercio minorista personas físicas.'];

        // ============================================================
        // OPCIONALES — combinaciones habituales
        // ============================================================
        yield ['EDS', 'IVA_GEN', 'optional', 'Combinación más habitual de profesionales y autónomos: EDS en IRPF + IVA General.'];
        yield ['EDS', 'IVA_CAJA', 'optional', 'Combinación habitual cuando se quiere diferir el devengo IVA al cobro real.'];
        yield ['EDN', 'IVA_GEN', 'optional', 'Combinación habitual de empresarios con cifra > 600.000 €.'];

        // SS combinaciones
        yield ['ASALARIADO_GEN', 'RG', 'required', 'Trabajador por cuenta ajena en IRPF tiene su correlato en Régimen General de SS.'];
        yield ['EDS', 'RETA', 'required', 'Autónomos en EDS cotizan obligatoriamente en RETA.'];
        yield ['EDN', 'RETA', 'required', 'Autónomos en EDN cotizan obligatoriamente en RETA.'];
        yield ['EO', 'RETA', 'required', 'Autónomos en EO cotizan obligatoriamente en RETA.'];
    }
}
