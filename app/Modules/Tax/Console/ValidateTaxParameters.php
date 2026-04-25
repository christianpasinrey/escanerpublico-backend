<?php

namespace Modules\Tax\Console;

use Illuminate\Console\Command;
use Modules\Tax\Models\AutonomoBracket;
use Modules\Tax\Models\SocialSecurityRate;
use Modules\Tax\Models\TaxBracket;
use Modules\Tax\Models\TaxParameter;
use Modules\Tax\Models\VatProductRate;

/**
 * Valida que los parámetros fiscales para un año dado están completos.
 *
 * Comprueba:
 * - Escala IRPF estatal (≥ 5 tramos type=irpf_general).
 * - Escalas autonómicas IRPF para MD, CT, AN, VC.
 * - Mínimos personales y familiares obligatorios.
 * - SS rates RG y RETA con contingencias mínimas.
 * - 15 autonomo_brackets.
 * - ≥ 25 vat_product_rates.
 */
class ValidateTaxParameters extends Command
{
    protected $signature = 'tax:validate {year : Año fiscal a validar (2023-2026)}';

    protected $description = 'Valida que los parámetros fiscales del año dado están completos.';

    /** Mínimos personales obligatorios. */
    private const REQUIRED_PARAMETER_KEYS = [
        'irpf.minimo_personal_general',
        'irpf.minimo_personal_mayor_65',
        'irpf.minimo_personal_mayor_75',
        'irpf.minimo_descendiente.primero',
        'irpf.minimo_descendiente.segundo',
        'irpf.minimo_descendiente.tercero',
        'irpf.minimo_descendiente.cuarto_y_siguientes',
        'irpf.minimo_descendiente.menor_3_anios',
        'irpf.minimo_ascendiente_mayor_65',
        'irpf.minimo_ascendiente_mayor_75',
        'irpf.minimo_discapacidad_general',
        'irpf.reduccion_rendimientos_trabajo',
        'irpf.tipo_retencion_administradores',
        'irpf.tipo_retencion_actividades_profesionales',
        'irpf.tipo_retencion_capital_mobiliario',
        'irpf.tipo_retencion_alquiler',
    ];

    private const REQUIRED_REGIONS = ['MD', 'CT', 'AN', 'VC'];

    private const REQUIRED_RG_CONTINGENCIES = [
        'contingencias_comunes',
        'desempleo_indefinido',
        'fp',
        'fogasa',
        'mei',
        'atep',
    ];

    private const REQUIRED_RETA_CONTINGENCIES = [
        'contingencias_comunes',
        'atep',
        'cese_actividad',
        'fp',
        'mei',
    ];

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        if ($year < 2023 || $year > 2030) {
            $this->error("Año fuera de rango: {$year}");

            return self::FAILURE;
        }

        $errors = [];

        // 1. Escala IRPF estatal completa.
        $stateBrackets = TaxBracket::query()
            ->where('year', $year)
            ->where('scope', 'state')
            ->where('type', 'irpf_general')
            ->count();
        if ($stateBrackets < 5) {
            $errors[] = "Escala IRPF estatal incompleta: {$stateBrackets} tramos (mínimo 5)";
        }

        // 2. Escalas autonómicas obligatorias.
        foreach (self::REQUIRED_REGIONS as $region) {
            $count = TaxBracket::query()
                ->where('year', $year)
                ->where('scope', 'regional')
                ->where('region_code', $region)
                ->where('type', 'irpf_general')
                ->count();
            if ($count < 4) {
                $errors[] = "Escala autonómica {$region} incompleta: {$count} tramos (mínimo 4)";
            }
        }

        // 3. Parámetros obligatorios.
        $existingKeys = TaxParameter::query()
            ->where('year', $year)
            ->whereNull('region_code')
            ->pluck('key')
            ->all();
        foreach (self::REQUIRED_PARAMETER_KEYS as $key) {
            if (! in_array($key, $existingKeys, true)) {
                $errors[] = "Falta tax_parameter obligatorio: {$key}";
            }
        }

        // 4. SS RG.
        foreach (self::REQUIRED_RG_CONTINGENCIES as $contingency) {
            $exists = SocialSecurityRate::query()
                ->where('year', $year)
                ->where('regime', 'RG')
                ->where('contingency', $contingency)
                ->exists();
            if (! $exists) {
                $errors[] = "Falta SocialSecurityRate RG/{$contingency}";
            }
        }

        // 5. SS RETA.
        foreach (self::REQUIRED_RETA_CONTINGENCIES as $contingency) {
            $exists = SocialSecurityRate::query()
                ->where('year', $year)
                ->where('regime', 'RETA')
                ->where('contingency', $contingency)
                ->exists();
            if (! $exists) {
                $errors[] = "Falta SocialSecurityRate RETA/{$contingency}";
            }
        }

        // 6. 15 tramos autónomos.
        $autonomoCount = AutonomoBracket::query()->where('year', $year)->count();
        if ($autonomoCount !== 15) {
            $errors[] = "autonomo_brackets debe tener 15 tramos para {$year}, encontrados: {$autonomoCount}";
        }

        // 7. ≥ 25 VAT rates.
        $vatCount = VatProductRate::query()->where('year', $year)->count();
        if ($vatCount < 25) {
            $errors[] = "vat_product_rates insuficiente para {$year}: {$vatCount} (mínimo 25)";
        }

        if (! empty($errors)) {
            $this->error("Validación fallida para el año {$year}:");
            foreach ($errors as $err) {
                $this->line('  - '.$err);
            }

            return self::FAILURE;
        }

        $this->info("✔ Parámetros fiscales completos para el año {$year}.");
        $this->table(['Tabla', 'Filas'], [
            ['tax_brackets',         (string) TaxBracket::query()->where('year', $year)->count()],
            ['tax_parameters',       (string) TaxParameter::query()->where('year', $year)->count()],
            ['social_security_rates', (string) SocialSecurityRate::query()->where('year', $year)->count()],
            ['autonomo_brackets',    (string) $autonomoCount],
            ['vat_product_rates',    (string) $vatCount],
        ]);

        return self::SUCCESS;
    }
}
