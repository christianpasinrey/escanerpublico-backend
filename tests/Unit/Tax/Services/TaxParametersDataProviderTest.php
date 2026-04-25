<?php

namespace Tests\Unit\Tax\Services;

use Database\Seeders\Modules\Tax\Parameters\TaxParametersDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxParametersDataProviderTest extends TestCase
{
    public static function yearsProvider(): array
    {
        return [
            [2023], [2024], [2025], [2026],
        ];
    }

    #[DataProvider('yearsProvider')]
    public function test_state_irpf_brackets_have_at_least_5(int $year): void
    {
        $brackets = TaxParametersDataProvider::stateIrpfBrackets($year);
        $this->assertGreaterThanOrEqual(5, count($brackets));
    }

    #[DataProvider('yearsProvider')]
    public function test_state_irpf_brackets_are_ordered(int $year): void
    {
        $brackets = TaxParametersDataProvider::stateIrpfBrackets($year);
        $previous = -1;
        foreach ($brackets as $b) {
            $this->assertGreaterThanOrEqual($previous, $b['from_amount']);
            $previous = $b['from_amount'];
        }
    }

    #[DataProvider('yearsProvider')]
    public function test_state_irpf_brackets_have_source_url(int $year): void
    {
        foreach (TaxParametersDataProvider::stateIrpfBrackets($year) as $b) {
            $this->assertArrayHasKey('source_url', $b);
            $this->assertNotEmpty($b['source_url']);
        }
    }

    #[DataProvider('yearsProvider')]
    public function test_regional_brackets_cover_top_4_ccaa(int $year): void
    {
        $regionals = TaxParametersDataProvider::regionalIrpfBrackets($year);
        foreach (['MD', 'CT', 'AN', 'VC'] as $region) {
            $this->assertArrayHasKey($region, $regionals, "Falta CCAA {$region} en {$year}");
            $this->assertGreaterThanOrEqual(4, count($regionals[$region]));
        }
    }

    #[DataProvider('yearsProvider')]
    public function test_autonomo_brackets_have_15_tramos(int $year): void
    {
        $brackets = TaxParametersDataProvider::autonomoBrackets($year);
        $this->assertCount(15, $brackets);

        // bracket_number 1..15
        $numbers = array_column($brackets, 'bracket_number');
        sort($numbers);
        $this->assertSame(range(1, 15), $numbers);
    }

    #[DataProvider('yearsProvider')]
    public function test_social_security_rates_cover_rg_and_reta(int $year): void
    {
        $rates = TaxParametersDataProvider::socialSecurityRates($year);
        $regimes = array_unique(array_column($rates, 'regime'));
        sort($regimes);

        $this->assertEqualsCanonicalizing(['RETA', 'RG'], $regimes);
    }

    #[DataProvider('yearsProvider')]
    public function test_vat_rates_have_at_least_25_entries(int $year): void
    {
        $rates = TaxParametersDataProvider::vatRates($year);
        $this->assertGreaterThanOrEqual(25, count($rates));
    }

    #[DataProvider('yearsProvider')]
    public function test_vat_rates_have_general_tipo(int $year): void
    {
        $rates = TaxParametersDataProvider::vatRates($year);
        $hasGeneral = false;
        foreach ($rates as $r) {
            if ($r['rate_type'] === 'general' && (float) $r['rate'] === 21.0) {
                $hasGeneral = true;
                break;
            }
        }
        $this->assertTrue($hasGeneral);
    }

    public function test_mei_increases_from_2023_to_2026(): void
    {
        $this->assertSame(0.6, TaxParametersDataProvider::meiTotal(2023));
        $this->assertSame(0.7, TaxParametersDataProvider::meiTotal(2024));
        $this->assertSame(0.8, TaxParametersDataProvider::meiTotal(2025));
        $this->assertSame(0.9, TaxParametersDataProvider::meiTotal(2026));
    }

    public function test_mei_split_is_5_to_1_employer_to_employee(): void
    {
        // Empresa = 5/6 del total, trabajador = 1/6
        foreach ([2023, 2024, 2025, 2026] as $year) {
            $total = TaxParametersDataProvider::meiTotal($year);
            $emp = TaxParametersDataProvider::meiEmpleador($year);
            $tra = TaxParametersDataProvider::meiTrabajador($year);

            $this->assertEqualsWithDelta($total, $emp + $tra, 0.0001);
            $this->assertEqualsWithDelta($total * 5 / 6, $emp, 0.001);
            $this->assertEqualsWithDelta($total * 1 / 6, $tra, 0.001);
        }
    }

    public function test_smi_increases_year_over_year(): void
    {
        $this->assertGreaterThan(
            TaxParametersDataProvider::smi(2023),
            TaxParametersDataProvider::smi(2024),
        );
        $this->assertGreaterThan(
            TaxParametersDataProvider::smi(2024),
            TaxParametersDataProvider::smi(2025),
        );
    }

    public function test_common_parameters_include_all_required_keys(): void
    {
        $params = TaxParametersDataProvider::commonParameters(2025);
        $keys = array_column($params, 'key');

        $required = [
            'irpf.minimo_personal_general',
            'irpf.minimo_personal_mayor_65',
            'irpf.minimo_descendiente.primero',
            'irpf.tipo_retencion_administradores',
            'irpf.tipo_retencion_actividades_profesionales',
            'iva.tipo_general',
            'iva.tipo_reducido',
            'iva.tipo_superreducido',
        ];
        foreach ($required as $key) {
            $this->assertContains($key, $keys, "Falta clave obligatoria {$key}");
        }
    }
}
