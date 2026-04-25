<?php

namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\ContractLot;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'contract_lot_id' => ContractLot::factory(),
            'company_id' => Company::factory(),
            'amount' => $this->faker->randomFloat(2, 1000, 500000),
            'amount_without_tax' => $this->faker->randomFloat(2, 800, 400000),
            'description' => $this->faker->sentence(),
            'procedure_type' => '9',
            'urgency' => '1',
            'award_date' => $this->faker->date(),
            'start_date' => $this->faker->date(),
            'formalization_date' => $this->faker->date(),
            'contract_number' => $this->faker->regexify('[A-Z0-9-]{10}'),
            'sme_awarded' => $this->faker->boolean(),
            'num_offers' => $this->faker->numberBetween(1, 10),
            'smes_received_tender_quantity' => $this->faker->numberBetween(0, 10),
            'result_code' => '8',
            'lower_tender_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'higher_tender_amount' => $this->faker->randomFloat(2, 10000, 100000),
        ];
    }
}
