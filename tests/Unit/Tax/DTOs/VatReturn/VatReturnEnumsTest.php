<?php

namespace Tests\Unit\Tax\DTOs\VatReturn;

use Modules\Tax\DTOs\VatReturn\VatReturnStatus;
use Modules\Tax\DTOs\VatReturn\VatTransactionCategory;
use Modules\Tax\DTOs\VatReturn\VatTransactionDirection;
use PHPUnit\Framework\TestCase;

class VatReturnEnumsTest extends TestCase
{
    public function test_direction_labels(): void
    {
        $this->assertStringContainsString('devengado', VatTransactionDirection::OUTGOING->label());
        $this->assertStringContainsString('soportado', VatTransactionDirection::INCOMING->label());
    }

    public function test_category_labels(): void
    {
        $this->assertSame('Operación interior', VatTransactionCategory::DOMESTIC->label());
        $this->assertSame('Operación intracomunitaria', VatTransactionCategory::INTRACOM->label());
        $this->assertSame('Importación', VatTransactionCategory::IMPORTS->label());
        $this->assertSame('Exportación', VatTransactionCategory::EXPORTS->label());
    }

    public function test_status_labels(): void
    {
        $this->assertSame('A ingresar', VatReturnStatus::A_INGRESAR->label());
        $this->assertSame('A compensar', VatReturnStatus::A_COMPENSAR->label());
        $this->assertSame('A devolver', VatReturnStatus::A_DEVOLVER->label());
    }

    public function test_status_values(): void
    {
        $this->assertSame('a_ingresar', VatReturnStatus::A_INGRESAR->value);
        $this->assertSame('a_compensar', VatReturnStatus::A_COMPENSAR->value);
        $this->assertSame('a_devolver', VatReturnStatus::A_DEVOLVER->value);
    }
}
