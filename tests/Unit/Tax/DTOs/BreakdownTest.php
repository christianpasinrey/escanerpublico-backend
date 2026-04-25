<?php

namespace Tests\Unit\Tax\DTOs;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\TaxRate;
use Tests\TestCase;

class BreakdownTest extends TestCase
{
    public function test_aggregates_by_category(): void
    {
        $lines = [
            new BreakdownLine(
                concept: 'Salario bruto',
                amount: new Money('30000.00'),
                category: BreakdownCategory::BASE,
            ),
            new BreakdownLine(
                concept: 'Cuota empleado contingencias comunes',
                amount: new Money('1860.00'),
                category: BreakdownCategory::CONTRIBUTION,
                base: new Money('30000.00'),
                rate: TaxRate::fromPercentage(6.2),
                legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-1994-14960',
            ),
            new BreakdownLine(
                concept: 'Retención IRPF',
                amount: new Money('4500.00'),
                category: BreakdownCategory::TAX,
            ),
            new BreakdownLine(
                concept: 'Salario neto',
                amount: new Money('23640.00'),
                category: BreakdownCategory::NET,
            ),
        ];

        $breakdown = new Breakdown(
            lines: $lines,
            netResult: new Money('23640.00'),
        );

        $this->assertSame('1860.00', $breakdown->totalByCategory(BreakdownCategory::CONTRIBUTION)->amount);
        $this->assertSame('4500.00', $breakdown->totalByCategory(BreakdownCategory::TAX)->amount);
        $this->assertSame('23640.00', $breakdown->netResult->amount);
    }

    public function test_summary_includes_all_categories(): void
    {
        $breakdown = new Breakdown(
            lines: [],
            netResult: Money::zero(),
        );

        $summary = $breakdown->summary();

        $this->assertCount(count(BreakdownCategory::cases()), $summary);
        foreach ($summary as $total) {
            $this->assertSame('0.00', $total->amount);
        }
    }

    public function test_serializes_to_json_with_all_fields(): void
    {
        $breakdown = new Breakdown(
            lines: [
                new BreakdownLine(
                    concept: 'Test',
                    amount: new Money('100.00'),
                    category: BreakdownCategory::TAX,
                ),
            ],
            netResult: new Money('100.00'),
            meta: ['scenario' => 'unit'],
        );

        $json = $breakdown->jsonSerialize();

        $this->assertArrayHasKey('lines', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('net_result', $json);
        $this->assertSame('EUR', $json['currency']);
        $this->assertSame(['scenario' => 'unit'], $json['meta']);
    }
}
