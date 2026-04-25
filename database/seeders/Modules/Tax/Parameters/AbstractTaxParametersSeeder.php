<?php

namespace Database\Seeders\Modules\Tax\Parameters;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\AutonomoBracket;
use Modules\Tax\Models\SocialSecurityRate;
use Modules\Tax\Models\TaxBracket;
use Modules\Tax\Models\TaxParameter;
use Modules\Tax\Models\VatProductRate;

/**
 * Base abstract para seeders anuales de parámetros fiscales.
 * Cada subclase concreta define el año y la tabla de parámetros.
 *
 * Los datos se sustituyen idempotentemente por (year, region, key).
 */
abstract class AbstractTaxParametersSeeder extends Seeder
{
    abstract protected function year(): int;

    /** @return list<array<string,mixed>> */
    abstract protected function stateIrpfBrackets(): array;

    /** @return array<string, list<array<string,mixed>>> */
    abstract protected function regionalIrpfBrackets(): array;

    /** @return list<array<string,mixed>> */
    abstract protected function savingsIrpfBrackets(): array;

    /** @return list<array<string,mixed>> */
    abstract protected function parameters(): array;

    /**
     * Parámetros con region_code != null. Por defecto, ninguno.
     *
     * @return list<array<string,mixed>>
     */
    protected function regionalParameters(): array
    {
        return [];
    }

    /** @return list<array<string,mixed>> */
    abstract protected function socialSecurityRates(): array;

    /** @return list<array<string,mixed>> */
    abstract protected function autonomoBrackets(): array;

    /** @return list<array<string,mixed>> */
    abstract protected function vatRates(): array;

    public function run(): void
    {
        $this->seedBrackets();
        $this->seedParameters();
        $this->seedSocialSecurityRates();
        $this->seedAutonomoBrackets();
        $this->seedVatRates();
    }

    protected function seedBrackets(): void
    {
        $year = $this->year();

        // Limpiamos brackets del año (re-seed idempotente).
        TaxBracket::query()->where('year', $year)->delete();

        foreach ($this->stateIrpfBrackets() as $row) {
            TaxBracket::create(array_merge($row, [
                'year' => $year,
                'scope' => 'state',
                'region_code' => null,
                'type' => $row['type'] ?? 'irpf_general',
            ]));
        }

        foreach ($this->savingsIrpfBrackets() as $row) {
            TaxBracket::create(array_merge($row, [
                'year' => $year,
                'scope' => 'state',
                'region_code' => null,
                'type' => $row['type'] ?? 'irpf_ahorro',
            ]));
        }

        foreach ($this->regionalIrpfBrackets() as $regionCode => $rows) {
            foreach ($rows as $row) {
                TaxBracket::create(array_merge($row, [
                    'year' => $year,
                    'scope' => 'regional',
                    'region_code' => $regionCode,
                    'type' => $row['type'] ?? 'irpf_general',
                ]));
            }
        }
    }

    protected function seedParameters(): void
    {
        $year = $this->year();

        TaxParameter::query()->where('year', $year)->delete();

        foreach ($this->parameters() as $row) {
            TaxParameter::create(array_merge($row, [
                'year' => $year,
                'region_code' => $row['region_code'] ?? null,
            ]));
        }

        foreach ($this->regionalParameters() as $row) {
            TaxParameter::create(array_merge($row, [
                'year' => $year,
            ]));
        }
    }

    protected function seedSocialSecurityRates(): void
    {
        $year = $this->year();

        SocialSecurityRate::query()->where('year', $year)->delete();

        foreach ($this->socialSecurityRates() as $row) {
            SocialSecurityRate::create(array_merge($row, ['year' => $year]));
        }
    }

    protected function seedAutonomoBrackets(): void
    {
        $year = $this->year();

        AutonomoBracket::query()->where('year', $year)->delete();

        foreach ($this->autonomoBrackets() as $row) {
            AutonomoBracket::create(array_merge($row, ['year' => $year]));
        }
    }

    protected function seedVatRates(): void
    {
        $year = $this->year();

        VatProductRate::query()->where('year', $year)->delete();

        foreach ($this->vatRates() as $row) {
            VatProductRate::create(array_merge($row, ['year' => $year]));
        }
    }
}
