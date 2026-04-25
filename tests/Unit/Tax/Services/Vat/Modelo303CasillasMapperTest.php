<?php

namespace Tests\Unit\Tax\Services\Vat;

use Modules\Tax\Services\Vat\Modelo303CasillasMapper;
use Modules\Tax\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class Modelo303CasillasMapperTest extends TestCase
{
    private Modelo303CasillasMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new Modelo303CasillasMapper;
    }

    public function test_maps_general_21_percent_to_casillas_01_03(): void
    {
        $vatBuckets = [
            '21.0000' => ['base' => new Money('1000.00'), 'vat' => new Money('210.00')],
        ];

        $casillas = $this->mapper->map(
            vatBucketsByRate: $vatBuckets,
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('210.00'),
            totalDeductibleBase: Money::zero(),
            totalDeductibleAmount: Money::zero(),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('210.00'),
            isLastPeriod: false,
        );

        $this->assertArrayHasKey('01', $casillas);
        $this->assertArrayHasKey('03', $casillas);
        $this->assertSame('1000.00', $casillas['01']->amount);
        $this->assertSame('210.00', $casillas['03']->amount);
        $this->assertSame('210.00', $casillas['27']->amount);
    }

    public function test_maps_reduced_10_percent_to_casillas_04_06(): void
    {
        $vatBuckets = [
            '10.0000' => ['base' => new Money('500.00'), 'vat' => new Money('50.00')],
        ];

        $casillas = $this->mapper->map(
            vatBucketsByRate: $vatBuckets,
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('50.00'),
            totalDeductibleBase: Money::zero(),
            totalDeductibleAmount: Money::zero(),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('50.00'),
            isLastPeriod: false,
        );

        $this->assertArrayHasKey('04', $casillas);
        $this->assertArrayHasKey('06', $casillas);
        $this->assertSame('500.00', $casillas['04']->amount);
        $this->assertSame('50.00', $casillas['06']->amount);
    }

    public function test_maps_super_reduced_4_percent_to_casillas_07_09(): void
    {
        $vatBuckets = [
            '4.0000' => ['base' => new Money('200.00'), 'vat' => new Money('8.00')],
        ];

        $casillas = $this->mapper->map(
            vatBucketsByRate: $vatBuckets,
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('8.00'),
            totalDeductibleBase: Money::zero(),
            totalDeductibleAmount: Money::zero(),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('8.00'),
            isLastPeriod: false,
        );

        $this->assertArrayHasKey('07', $casillas);
        $this->assertArrayHasKey('09', $casillas);
        $this->assertSame('200.00', $casillas['07']->amount);
        $this->assertSame('8.00', $casillas['09']->amount);
    }

    public function test_maps_deductible_to_casillas_28_29_45_46(): void
    {
        $casillas = $this->mapper->map(
            vatBucketsByRate: ['21.0000' => ['base' => new Money('1000.00'), 'vat' => new Money('210.00')]],
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('210.00'),
            totalDeductibleBase: new Money('400.00'),
            totalDeductibleAmount: new Money('84.00'),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('126.00'),
            isLastPeriod: false,
        );

        $this->assertSame('400.00', $casillas['28']->amount);
        $this->assertSame('84.00', $casillas['29']->amount);
        $this->assertSame('84.00', $casillas['45']->amount);
        $this->assertSame('126.00', $casillas['46']->amount);
    }

    public function test_maps_negative_quota_to_casilla_72_when_intermediate_period(): void
    {
        $casillas = $this->mapper->map(
            vatBucketsByRate: ['21.0000' => ['base' => new Money('100.00'), 'vat' => new Money('21.00')]],
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('21.00'),
            totalDeductibleBase: new Money('500.00'),
            totalDeductibleAmount: new Money('105.00'),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('-84.00'),
            isLastPeriod: false,
            requestRefund: false,
        );

        $this->assertArrayHasKey('72', $casillas);
        $this->assertArrayNotHasKey('73', $casillas);
        $this->assertSame('84.00', $casillas['72']->amount);
    }

    public function test_maps_negative_quota_to_casilla_73_when_last_period_and_refund_requested(): void
    {
        $casillas = $this->mapper->map(
            vatBucketsByRate: ['21.0000' => ['base' => new Money('100.00'), 'vat' => new Money('21.00')]],
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('21.00'),
            totalDeductibleBase: new Money('500.00'),
            totalDeductibleAmount: new Money('105.00'),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('-84.00'),
            isLastPeriod: true,
            requestRefund: true,
        );

        $this->assertArrayHasKey('73', $casillas);
        $this->assertSame('84.00', $casillas['73']->amount);
    }

    public function test_maps_carry_forward_to_casilla_78(): void
    {
        $casillas = $this->mapper->map(
            vatBucketsByRate: ['21.0000' => ['base' => new Money('1000.00'), 'vat' => new Money('210.00')]],
            surchargeBucketsByRate: [],
            totalVatAccrued: new Money('210.00'),
            totalDeductibleBase: Money::zero(),
            totalDeductibleAmount: Money::zero(),
            previousQuotaCarryForward: new Money('50.00'),
            liquidQuota: new Money('160.00'),
            isLastPeriod: false,
        );

        $this->assertArrayHasKey('78', $casillas);
        $this->assertSame('50.00', $casillas['78']->amount);
    }

    public function test_maps_recargo_equivalencia_5_20_to_casillas_19_21(): void
    {
        $casillas = $this->mapper->map(
            vatBucketsByRate: ['21.0000' => ['base' => new Money('1000.00'), 'vat' => new Money('210.00')]],
            surchargeBucketsByRate: [
                '5.2000' => ['base' => new Money('1000.00'), 'vat' => new Money('52.00')],
            ],
            totalVatAccrued: new Money('262.00'),
            totalDeductibleBase: Money::zero(),
            totalDeductibleAmount: Money::zero(),
            previousQuotaCarryForward: Money::zero(),
            liquidQuota: new Money('262.00'),
            isLastPeriod: false,
        );

        $this->assertArrayHasKey('19', $casillas);
        $this->assertArrayHasKey('21', $casillas);
        $this->assertSame('1000.00', $casillas['19']->amount);
        $this->assertSame('52.00', $casillas['21']->amount);
    }

    public function test_coverage_lists_covered_and_uncovered(): void
    {
        $coverage = $this->mapper->coverage();

        $this->assertArrayHasKey('covered', $coverage);
        $this->assertArrayHasKey('uncovered', $coverage);
        $this->assertContains('27', $coverage['covered']);
        $this->assertContains('71', $coverage['covered']);
        $this->assertContains('10', $coverage['uncovered']);
        $this->assertContains('11', $coverage['uncovered']);
    }
}
