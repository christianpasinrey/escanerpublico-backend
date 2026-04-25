<?php

namespace Tests\Unit\Tax\DTOs\FractionalPayment;

use InvalidArgumentException;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentInput;
use Modules\Tax\DTOs\FractionalPayment\FractionalPaymentModel;
use Modules\Tax\DTOs\IncomeTax\TaxpayerSituation;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;
use PHPUnit\Framework\TestCase;

/**
 * Tests del DTO FractionalPaymentInput (M8 — modelos 130/131).
 *
 * Verifica las validaciones de dominio del constructor: compatibilidad
 * modelo/régimen, rango de trimestre, importes no negativos, requisitos
 * específicos por modelo.
 */
class FractionalPaymentInputTest extends TestCase
{
    private function baseFactory(array $overrides = []): FractionalPaymentInput
    {
        $defaults = [
            'model' => FractionalPaymentModel::MODELO_130,
            'regime' => new RegimeCode('EDN'),
            'year' => FiscalYear::fromInt(2025),
            'quarter' => 1,
            'taxpayerSituation' => new TaxpayerSituation,
            'cumulativeGrossRevenue' => new Money('12000.00'),
            'cumulativeDeductibleExpenses' => new Money('3000.00'),
        ];
        $args = array_merge($defaults, $overrides);

        return new FractionalPaymentInput(
            model: $args['model'],
            regime: $args['regime'],
            year: $args['year'],
            quarter: $args['quarter'],
            taxpayerSituation: $args['taxpayerSituation'],
            cumulativeGrossRevenue: $args['cumulativeGrossRevenue'],
            cumulativeDeductibleExpenses: $args['cumulativeDeductibleExpenses'],
            activityCode: $args['activityCode'] ?? null,
            eoModulesData: $args['eoModulesData'] ?? null,
            salariedEmployees: $args['salariedEmployees'] ?? 0,
            withholdingsApplied: $args['withholdingsApplied'] ?? new Money('0.00'),
            previousQuartersPayments: $args['previousQuartersPayments'] ?? new Money('0.00'),
        );
    }

    public function test_construye_modelo_130_con_edn(): void
    {
        $input = $this->baseFactory();
        $this->assertSame('130', $input->model->value);
        $this->assertSame('EDN', $input->regime->code);
        $this->assertSame(1, $input->quarter);
        $this->assertSame('1T 2025', $input->quarterLabel());
    }

    public function test_construye_modelo_130_con_eds(): void
    {
        $input = $this->baseFactory(['regime' => new RegimeCode('EDS')]);
        $this->assertSame('EDS', $input->regime->code);
    }

    public function test_construye_modelo_131_con_eo(): void
    {
        $input = $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '721.2',
            'eoModulesData' => ['personal_no_asalariado' => 1],
        ]);
        $this->assertSame('131', $input->model->value);
        $this->assertSame('EO', $input->regime->code);
        $this->assertSame('721.2', $input->activityCode);
    }

    public function test_rechaza_modelo_130_con_eo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no es compatible/i');
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_130,
            'regime' => new RegimeCode('EO'),
        ]);
    }

    public function test_rechaza_modelo_131_con_edn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no es compatible/i');
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EDN'),
            'activityCode' => '721.2',
        ]);
    }

    public function test_rechaza_modelo_131_con_eds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EDS'),
            'activityCode' => '721.2',
        ]);
    }

    public function test_rechaza_regimen_no_irpf(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/'irpf'/");
        $this->baseFactory(['regime' => new RegimeCode('IVA_GEN')]);
    }

    public function test_rechaza_quarter_fuera_de_rango_alto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trimestre/i');
        $this->baseFactory(['quarter' => 5]);
    }

    public function test_rechaza_quarter_fuera_de_rango_bajo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory(['quarter' => 0]);
    }

    public function test_rechaza_ingresos_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cumulativeGrossRevenue/i');
        $this->baseFactory(['cumulativeGrossRevenue' => new Money('-100.00')]);
    }

    public function test_rechaza_gastos_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cumulativeDeductibleExpenses/i');
        $this->baseFactory(['cumulativeDeductibleExpenses' => new Money('-100.00')]);
    }

    public function test_rechaza_retenciones_negativas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/withholdingsApplied/i');
        $this->baseFactory(['withholdingsApplied' => new Money('-1.00')]);
    }

    public function test_rechaza_pagos_previos_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory(['previousQuartersPayments' => new Money('-1.00')]);
    }

    public function test_rechaza_salaried_employees_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '721.2',
            'salariedEmployees' => -1,
        ]);
    }

    public function test_modelo_131_requiere_activity_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/activityCode/i');
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => null,
        ]);
    }

    public function test_modelo_131_rechaza_activity_code_vacio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '',
        ]);
    }

    public function test_eo_modules_data_keys_deben_ser_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '721.2',
            'eoModulesData' => [123 => 5],
        ]);
    }

    public function test_eo_modules_data_values_deben_ser_numericos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '721.2',
            'eoModulesData' => ['personal_no_asalariado' => 'two'],
        ]);
    }

    public function test_eo_modules_data_values_no_pueden_ser_negativos(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->baseFactory([
            'model' => FractionalPaymentModel::MODELO_131,
            'regime' => new RegimeCode('EO'),
            'activityCode' => '721.2',
            'eoModulesData' => ['personal_no_asalariado' => -1],
        ]);
    }

    public function test_compatibility_helper_devuelve_regimenes_correctos(): void
    {
        $this->assertSame(['EDN', 'EDS'], FractionalPaymentModel::MODELO_130->compatibleRegimes());
        $this->assertSame(['EO'], FractionalPaymentModel::MODELO_131->compatibleRegimes());
    }

    public function test_json_serialize_expone_period_label(): void
    {
        $input = $this->baseFactory(['quarter' => 3]);
        $payload = $input->jsonSerialize();
        $this->assertSame('3T 2025', $payload['period_label']);
        $this->assertSame('130', $payload['model']);
        $this->assertSame(2025, $payload['year']);
        $this->assertSame(3, $payload['quarter']);
    }
}
