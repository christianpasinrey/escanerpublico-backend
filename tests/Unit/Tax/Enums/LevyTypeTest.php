<?php

namespace Tests\Unit\Tax\Enums;

use Modules\Tax\Enums\LevyType;
use PHPUnit\Framework\TestCase;

class LevyTypeTest extends TestCase
{
    public function test_has_three_cases(): void
    {
        $this->assertCount(3, LevyType::cases());
    }

    public function test_values_exposes_string_values(): void
    {
        $this->assertSame(['impuesto', 'tasa', 'contribucion'], LevyType::values());
    }

    public function test_labels_returns_human_readable_names(): void
    {
        $labels = LevyType::labels();

        $this->assertSame('Impuesto', $labels['impuesto']);
        $this->assertSame('Tasa', $labels['tasa']);
        $this->assertSame('Contribución especial', $labels['contribucion']);
    }

    public function test_label_returns_per_case_label(): void
    {
        $this->assertSame('Impuesto', LevyType::Impuesto->label());
        $this->assertSame('Tasa', LevyType::Tasa->label());
        $this->assertSame('Contribución especial', LevyType::Contribucion->label());
    }

    public function test_can_be_built_from_value(): void
    {
        $this->assertSame(LevyType::Impuesto, LevyType::from('impuesto'));
        $this->assertSame(LevyType::Tasa, LevyType::from('tasa'));
        $this->assertNull(LevyType::tryFrom('unknown'));
    }
}
