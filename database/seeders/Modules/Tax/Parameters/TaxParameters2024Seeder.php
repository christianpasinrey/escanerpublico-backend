<?php

namespace Database\Seeders\Modules\Tax\Parameters;

class TaxParameters2024Seeder extends AbstractTaxParametersSeeder
{
    protected function year(): int
    {
        return 2024;
    }

    protected function stateIrpfBrackets(): array
    {
        return TaxParametersDataProvider::stateIrpfBrackets($this->year());
    }

    protected function regionalIrpfBrackets(): array
    {
        return TaxParametersDataProvider::regionalIrpfBrackets($this->year());
    }

    protected function savingsIrpfBrackets(): array
    {
        return TaxParametersDataProvider::savingsIrpfBrackets($this->year());
    }

    protected function parameters(): array
    {
        return TaxParametersDataProvider::commonParameters($this->year());
    }

    protected function regionalParameters(): array
    {
        return TaxParametersDataProvider::regionalParameters($this->year());
    }

    protected function socialSecurityRates(): array
    {
        return TaxParametersDataProvider::socialSecurityRates($this->year());
    }

    protected function autonomoBrackets(): array
    {
        return TaxParametersDataProvider::autonomoBrackets($this->year());
    }

    protected function vatRates(): array
    {
        return TaxParametersDataProvider::vatRates($this->year());
    }
}
