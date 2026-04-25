<?php

namespace Tests\Unit\Tax\Enums;

use Modules\Tax\Enums\Scope;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    public function test_has_three_cases(): void
    {
        $this->assertCount(3, Scope::cases());
    }

    public function test_values_exposes_string_values(): void
    {
        $this->assertSame(['state', 'regional', 'local'], Scope::values());
    }

    public function test_labels_returns_human_readable_names(): void
    {
        $labels = Scope::labels();

        $this->assertSame('Estatal', $labels['state']);
        $this->assertSame('Autonómico', $labels['regional']);
        $this->assertSame('Local', $labels['local']);
    }

    public function test_label_returns_per_case_label(): void
    {
        $this->assertSame('Estatal', Scope::State->label());
        $this->assertSame('Autonómico', Scope::Regional->label());
        $this->assertSame('Local', Scope::Local->label());
    }

    public function test_can_be_built_from_value(): void
    {
        $this->assertSame(Scope::State, Scope::from('state'));
        $this->assertNull(Scope::tryFrom('foo'));
    }
}
