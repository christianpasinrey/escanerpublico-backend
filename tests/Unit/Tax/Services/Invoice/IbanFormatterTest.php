<?php

namespace Tests\Unit\Tax\Services\Invoice;

use InvalidArgumentException;
use Modules\Tax\Services\Invoice\IbanFormatter;
use PHPUnit\Framework\TestCase;

class IbanFormatterTest extends TestCase
{
    private IbanFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new IbanFormatter;
    }

    /**
     * IBAN ES de ejemplo del Banco de España (válido mod-97):
     *   ES91 2100 0418 4502 0005 1332
     */
    public function test_formats_valid_iban_in_groups_of_4(): void
    {
        $formatted = $this->formatter->format('ES9121000418450200051332');
        $this->assertSame('ES91 2100 0418 4502 0005 1332', $formatted);
    }

    public function test_formats_valid_iban_with_existing_spaces(): void
    {
        $formatted = $this->formatter->format('ES91 2100 0418 4502 0005 1332');
        $this->assertSame('ES91 2100 0418 4502 0005 1332', $formatted);
    }

    public function test_rejects_invalid_iban(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->formatter->format('ES00 0000 0000 0000 0000 0000');
    }

    public function test_validates_iban_with_check_digit(): void
    {
        $this->assertTrue($this->formatter->isValid('ES91 2100 0418 4502 0005 1332'));
        $this->assertFalse($this->formatter->isValid('ES12 3456 7890 1234 5678 9012'));
        $this->assertFalse($this->formatter->isValid('not-an-iban'));
    }

    public function test_masks_iban_leaves_last_4_visible(): void
    {
        $masked = $this->formatter->mask('ES9121000418450200051332');
        $this->assertStringEndsWith('1332', str_replace(' ', '', $masked));
        $this->assertStringContainsString('*', $masked);
    }
}
