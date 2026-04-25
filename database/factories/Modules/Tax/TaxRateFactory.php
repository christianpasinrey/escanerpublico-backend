<?php

namespace Database\Factories\Modules\Tax;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Models\TaxType;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'tax_type_id' => TaxType::factory(),
            'year' => 2025,
            'region_code' => null,
            'rate' => 21.0000,
            'base_min' => null,
            'base_max' => null,
            'fixed_amount' => null,
            'conditions' => null,
            'source_url' => 'https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740',
            'valid_from' => '2025-01-01',
            'valid_to' => null,
        ];
    }

    public function fixed(float $amount): self
    {
        return $this->state(fn () => [
            'rate' => null,
            'fixed_amount' => $amount,
        ]);
    }

    public function forYear(int $year): self
    {
        return $this->state(fn () => [
            'year' => $year,
            'valid_from' => "{$year}-01-01",
        ]);
    }
}
