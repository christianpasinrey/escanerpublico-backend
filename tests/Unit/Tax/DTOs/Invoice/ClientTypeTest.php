<?php

namespace Tests\Unit\Tax\DTOs\Invoice;

use Modules\Tax\DTOs\Invoice\ClientType;
use PHPUnit\Framework\TestCase;

class ClientTypeTest extends TestCase
{
    public function test_empresa_withholds_irpf(): void
    {
        $this->assertTrue(ClientType::EMPRESA->withholdsIrpf());
    }

    public function test_particular_does_not_withhold_irpf(): void
    {
        $this->assertFalse(ClientType::PARTICULAR->withholdsIrpf());
    }

    public function test_label_in_spanish(): void
    {
        $this->assertSame('Empresa o autónomo', ClientType::EMPRESA->label());
        $this->assertSame('Consumidor final', ClientType::PARTICULAR->label());
    }
}
