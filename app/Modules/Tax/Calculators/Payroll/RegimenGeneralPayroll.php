<?php

namespace Modules\Tax\Calculators\Payroll;

use Modules\Tax\DTOs\Breakdown;
use Modules\Tax\DTOs\BreakdownCategory;
use Modules\Tax\DTOs\BreakdownLine;
use Modules\Tax\DTOs\Payroll\ContractType;
use Modules\Tax\DTOs\Payroll\PayrollInput;
use Modules\Tax\DTOs\Payroll\PayrollResult;
use Modules\Tax\Services\IrpfScaleResolver;
use Modules\Tax\Services\MinimumPersonalCalculator;
use Modules\Tax\Services\SocialSecurityResolver;
use Modules\Tax\Services\TaxParameterRepository;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegionCode;
use Modules\Tax\ValueObjects\TaxRate;
use RuntimeException;

/**
 * Calcula la nómina anual y mensual de un asalariado bajo Régimen General SS
 * + tributación IRPF estatal+autonómica.
 *
 * El cálculo es paso a paso y cada paso genera una BreakdownLine con
 * referencia legal al BOE para que el ciudadano pueda auditar el cálculo.
 *
 * Esto es un cálculo de **retención** anual: equivalente al cálculo del
 * tipo de retención IRPF (RD 439/2007 art. 80-89). No es una declaración IRPF
 * (eso es M6).
 *
 * Disclaimer: cálculo informativo, no asesoramiento fiscal. La nómina real
 * depende del convenio colectivo, complementos, antigüedad, dietas, horas
 * extra y otros conceptos que esta calculadora simplifica.
 */
class RegimenGeneralPayroll
{
    private const BOE_LIRPF = 'https://www.boe.es/buscar/act.php?id=BOE-A-2006-20764';

    private const BOE_LIRPF_REGLAMENTO = 'https://www.boe.es/buscar/act.php?id=BOE-A-2007-6820';

    private const BOE_LGSS = 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11724';

    private const BOE_MEI = 'https://www.boe.es/buscar/act.php?id=BOE-A-2023-2954';

    public function __construct(
        private readonly TaxParameterRepository $repository,
        private readonly SocialSecurityResolver $ssResolver,
        private readonly IrpfScaleResolver $irpfResolver,
        private readonly MinimumPersonalCalculator $minimumCalculator,
    ) {}

    public function calculate(PayrollInput $input): PayrollResult
    {
        $this->validateGrossAboveMinimum($input);

        $lines = [];

        // 1. Bruto anual (BASE)
        $grossAnnual = $input->grossAnnual;
        $lines[] = new BreakdownLine(
            concept: 'Salario bruto anual',
            amount: $grossAnnual,
            category: BreakdownCategory::BASE,
            base: $grossAnnual,
            legalReference: 'https://www.boe.es/buscar/act.php?id=BOE-A-2015-11430',
            explanation: 'Importe total bruto anual percibido en concepto de retribución por cuenta ajena, antes de cotizaciones a Seguridad Social y retención de IRPF.',
        );

        // 2. Base SS contingencias comunes — capada entre base mín y máx.
        //
        // La cotización a la Seguridad Social en España es siempre mensual (12
        // mensualidades, las pagas extra se PRORRATEAN). Por eso usamos bruto/12
        // (no /paymentsCount) para calcular la base de cotización mensual.
        // Después la anualizamos × 12 para presentar la cuota anual.
        // Fuente: art. 147 TRLGSS — Real Decreto Legislativo 8/2015.
        $monthlyGross = $input->monthlyGross();
        $monthlyBaseRaw = $grossAnnual->divide('12');
        $cappedMonthlyBase = $this->ssResolver->cappedMonthlyBase(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            $monthlyBaseRaw,
        );
        $cappedAnnualBase = $cappedMonthlyBase->multiply('12');

        $ccQuotaAnnual = $this->employeeContribution(
            $input,
            $cappedAnnualBase,
            SocialSecurityResolver::CONTINGENCY_COMMON,
            'Cotización contingencias comunes (trabajador)',
            self::BOE_LGSS,
            'Tipo del trabajador 4,70 % sobre la base de cotización (capada entre base mínima y máxima).',
            $lines,
        );

        // 3. Desempleo (según contrato)
        $unemploymentContingency = $input->contractType->unemploymentContingency();
        $unemploymentLabel = $input->contractType === ContractType::Indefinido
            ? 'Indefinido (1,55 %)'
            : 'Temporal (1,60 %)';
        $unemploymentQuotaAnnual = $this->employeeContribution(
            $input,
            $cappedAnnualBase,
            $unemploymentContingency,
            "Cotización desempleo (trabajador) — {$unemploymentLabel}",
            self::BOE_LGSS,
            'Tipo del trabajador por desempleo según el tipo de contrato (Orden anual de cotización).',
            $lines,
        );

        // 4. FP (0.10 % trabajador)
        $fpQuotaAnnual = $this->employeeContribution(
            $input,
            $cappedAnnualBase,
            SocialSecurityResolver::CONTINGENCY_FP,
            'Cotización Formación Profesional (trabajador)',
            self::BOE_LGSS,
            'Aportación a Formación Profesional 0,10 % a cargo del trabajador.',
            $lines,
        );

        // 5. MEI trabajador (1/6 del MEI total: 0.10 % en 2023, 0.117 % en 2024, 0.133 % en 2025, 0.15 % en 2026)
        $meiQuotaAnnual = $this->employeeContribution(
            $input,
            $cappedAnnualBase,
            SocialSecurityResolver::CONTINGENCY_MEI,
            'Mecanismo de Equidad Intergeneracional (trabajador)',
            self::BOE_MEI,
            'MEI introducido por RD-ley 13/2022. El trabajador asume 1/6 del tipo total: 0,10 % en 2023, 0,117 % en 2024, 0,133 % en 2025 y 0,15 % en 2026.',
            $lines,
        );

        // 6. Total cuota empleado SS
        $employeeSsTotal = $ccQuotaAnnual
            ->add($unemploymentQuotaAnnual)
            ->add($fpQuotaAnnual)
            ->add($meiQuotaAnnual);

        $lines[] = new BreakdownLine(
            concept: 'Total cotización trabajador a la Seguridad Social',
            amount: $employeeSsTotal,
            category: BreakdownCategory::CONTRIBUTION,
            base: $cappedAnnualBase,
            legalReference: self::BOE_LGSS,
            explanation: 'Suma de contingencias comunes, desempleo, FP y MEI a cargo del trabajador.',
        );

        // 7. Reducción rendimientos del trabajo (Ley 35/2006 art. 20)
        $reduction = $this->workIncomeReduction($input, $grossAnnual, $employeeSsTotal);
        if (! $reduction->isZero()) {
            $lines[] = new BreakdownLine(
                concept: 'Reducción por rendimientos del trabajo (art. 20 LIRPF)',
                amount: $reduction,
                category: BreakdownCategory::REDUCTION,
                base: $grossAnnual->subtract($employeeSsTotal),
                legalReference: self::BOE_LIRPF,
                explanation: 'Reducción aplicable a rendimientos netos del trabajo bajos según escala del art. 20 LIRPF (rendimiento ≤ 14.852 €: 6.498 €; entre 14.852 € y 19.747,50 €: decreciente; > 19.747,50 €: 0).',
            );
        }

        // 8. Base liquidable IRPF = bruto - cuotas SS empleado - reducción trabajo
        $taxableBase = $grossAnnual->subtract($employeeSsTotal)->subtract($reduction);
        if ($taxableBase->isNegative()) {
            $taxableBase = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Base liquidable IRPF',
            amount: $taxableBase,
            category: BreakdownCategory::BASE,
            base: $grossAnnual,
            legalReference: self::BOE_LIRPF,
            explanation: 'Base liquidable IRPF = bruto - cotizaciones del trabajador a la SS - reducción rendimientos del trabajo.',
        );

        // 9. Mínimo personal y familiar (sólo lo informamos como línea de reducción
        //    para transparencia; su efecto se aplica en la cuota — punto 12).
        $minimumPersonal = $this->minimumCalculator->calculate($input);
        $lines[] = new BreakdownLine(
            concept: 'Mínimo personal y familiar (art. 56-61 LIRPF)',
            amount: $minimumPersonal,
            category: BreakdownCategory::REDUCTION,
            legalReference: self::BOE_LIRPF,
            explanation: 'Cuantía exenta del IRPF: mínimo personal del contribuyente (5.550 €) + mínimos por descendientes, ascendientes y discapacidad. No reduce la base sino que limita la cuota: la escala se aplica a la base completa y al mínimo, restándose la segunda cuota.',
        );

        // 10. Cuota íntegra estatal: cuota(base) - cuota(mínimo)
        $stateQuotaOnBase = $this->irpfResolver->applyStateScale($input->year, $taxableBase);
        $stateQuotaOnMinimum = $this->irpfResolver->applyStateScale($input->year, $minimumPersonal);
        $stateQuota = $stateQuotaOnBase->subtract($stateQuotaOnMinimum);
        if ($stateQuota->isNegative()) {
            $stateQuota = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Cuota íntegra estatal IRPF (art. 63 LIRPF)',
            amount: $stateQuota,
            category: BreakdownCategory::TAX,
            base: $taxableBase,
            legalReference: self::BOE_LIRPF,
            explanation: 'Cuota íntegra estatal: se aplica la escala estatal sobre la base liquidable y sobre el mínimo personal+familiar; la segunda se resta de la primera.',
        );

        // 11. Cuota íntegra autonómica
        $regionalQuota = Money::zero();
        if (! $input->region->isState()) {
            $regionalOnBase = $this->irpfResolver->applyRegionalScale($input->year, $input->region, $taxableBase);
            $regionalOnMinimum = $this->irpfResolver->applyRegionalScale($input->year, $input->region, $minimumPersonal);
            $regionalQuota = $regionalOnBase->subtract($regionalOnMinimum);
            if ($regionalQuota->isNegative()) {
                $regionalQuota = Money::zero();
            }

            $lines[] = new BreakdownLine(
                concept: "Cuota íntegra autonómica IRPF — {$input->region->name()}",
                amount: $regionalQuota,
                category: BreakdownCategory::TAX,
                base: $taxableBase,
                legalReference: $this->regionalLegalReference($input->region),
                explanation: 'Cuota íntegra autonómica: misma técnica de cálculo que la estatal pero aplicando la escala propia de la CCAA.',
            );
        }

        // 12. Cuota total
        $totalQuota = $stateQuota->add($regionalQuota);

        $lines[] = new BreakdownLine(
            concept: 'Retención IRPF anual estimada',
            amount: $totalQuota,
            category: BreakdownCategory::TAX,
            base: $taxableBase,
            legalReference: self::BOE_LIRPF_REGLAMENTO,
            explanation: 'Suma de la cuota estatal y autonómica. Es el importe que la empresa retiene del bruto anual y que se ingresa en Hacienda mediante el modelo 111 (trimestral) y 190 (anual).',
        );

        // 13. Neto anual = bruto - cuotas SS empleado - retención IRPF
        $netAnnual = $grossAnnual->subtract($employeeSsTotal)->subtract($totalQuota);
        if ($netAnnual->isNegative()) {
            $netAnnual = Money::zero();
        }

        $lines[] = new BreakdownLine(
            concept: 'Neto anual',
            amount: $netAnnual,
            category: BreakdownCategory::NET,
            base: $grossAnnual,
            legalReference: self::BOE_LIRPF_REGLAMENTO,
            explanation: 'Importe neto anual percibido por el trabajador después de descontar cotizaciones a la SS y retención de IRPF.',
        );

        // 14. Cuotas empresa (informativas)
        $employerCC = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_COMMON,
            $cappedAnnualBase,
        );
        $employerUnemployment = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            $unemploymentContingency,
            $cappedAnnualBase,
        );
        $employerFp = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_FP,
            $cappedAnnualBase,
        );
        $employerFogasa = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_FOGASA,
            $cappedAnnualBase,
        );
        $employerMei = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_MEI,
            $cappedAnnualBase,
        );
        $employerAtep = $this->ssResolver->employerQuota(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            SocialSecurityResolver::CONTINGENCY_ATEP,
            $cappedAnnualBase,
        );

        $employerTotal = $employerCC
            ->add($employerUnemployment)
            ->add($employerFp)
            ->add($employerFogasa)
            ->add($employerMei)
            ->add($employerAtep);

        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa contingencias comunes (23,60 %)',
            amount: $employerCC,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            rate: TaxRate::fromPercentage(23.60),
            legalReference: self::BOE_LGSS,
            explanation: 'Aportación de la empresa a contingencias comunes de la Seguridad Social.',
        );
        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa desempleo',
            amount: $employerUnemployment,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            legalReference: self::BOE_LGSS,
            explanation: 'Aportación de la empresa por desempleo: 5,50 % indefinido, 6,70 % temporal.',
        );
        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa Formación Profesional (0,60 %)',
            amount: $employerFp,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            rate: TaxRate::fromPercentage(0.60),
            legalReference: self::BOE_LGSS,
        );
        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa FOGASA (0,20 %)',
            amount: $employerFogasa,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            rate: TaxRate::fromPercentage(0.20),
            legalReference: self::BOE_LGSS,
            explanation: 'Fondo de Garantía Salarial: garantiza salarios e indemnizaciones en caso de insolvencia empresarial.',
        );
        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa MEI',
            amount: $employerMei,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            legalReference: self::BOE_MEI,
            explanation: 'La empresa asume 5/6 del MEI total: 0,50 % en 2023, 0,583 % en 2024, 0,667 % en 2025 y 0,75 % en 2026.',
        );
        $lines[] = new BreakdownLine(
            concept: 'Cotización empresa AT/EP (1,50 % representativo)',
            amount: $employerAtep,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            rate: TaxRate::fromPercentage(1.50),
            legalReference: 'https://www.boe.es/buscar/doc.php?id=BOE-A-2007-22390',
            explanation: 'Accidentes de trabajo y enfermedad profesional. Tipo variable según CNAE (RD 2930/1979). Usamos 1,50 % representativo de oficinas y servicios.',
        );

        $lines[] = new BreakdownLine(
            concept: 'Total cotización empresa a la Seguridad Social',
            amount: $employerTotal,
            category: BreakdownCategory::INFO,
            base: $cappedAnnualBase,
            legalReference: self::BOE_LGSS,
            explanation: 'Suma de todas las cotizaciones a cargo de la empresa.',
        );

        // 15. Coste total empresa
        $companyTotalCost = $grossAnnual->add($employerTotal);
        $lines[] = new BreakdownLine(
            concept: 'Coste total empresa',
            amount: $companyTotalCost,
            category: BreakdownCategory::INFO,
            base: $grossAnnual,
            legalReference: self::BOE_LGSS,
            explanation: 'Bruto anual del trabajador + cotizaciones empresariales a la SS. Es el coste total que asume la empresa por este puesto.',
        );

        $effectiveRate = $this->effectiveRate($grossAnnual, $totalQuota);

        $monthlyNet = $netAnnual->divide($input->paymentsCount);

        $breakdown = new Breakdown(
            lines: array_values($lines),
            netResult: $netAnnual,
            currency: $grossAnnual->currency,
            meta: [
                'year' => $input->year->year,
                'region' => $input->region->code,
                'region_name' => $input->region->name(),
                'contract_type' => $input->contractType->value,
                'payments_count' => $input->paymentsCount,
                'disclaimer' => 'Calculadora informativa: no sustituye al asesoramiento fiscal ni a la nómina real. Convenios colectivos, complementos, antigüedad, dietas y otras circunstancias no se tienen en cuenta.',
            ],
        );

        return new PayrollResult(
            breakdown: $breakdown,
            monthlyGross: $monthlyGross,
            monthlyNet: $monthlyNet,
            annualGross: $grossAnnual,
            annualNet: $netAnnual,
            effectiveTaxRate: $effectiveRate,
            companyTotalCost: $companyTotalCost,
        );
    }

    /**
     * @param  list<BreakdownLine>  $lines
     */
    private function employeeContribution(
        PayrollInput $input,
        Money $cappedAnnualBase,
        string $contingency,
        string $concept,
        string $legalReference,
        string $explanation,
        array &$lines,
    ): Money {
        $rate = $this->ssResolver->employeeRate(
            $input->year,
            SocialSecurityResolver::REGIMEN_GENERAL,
            $contingency,
        );
        $quota = $cappedAnnualBase->applyRate($rate);

        $lines[] = new BreakdownLine(
            concept: $concept,
            amount: $quota,
            category: BreakdownCategory::CONTRIBUTION,
            base: $cappedAnnualBase,
            rate: $rate,
            legalReference: $legalReference,
            explanation: $explanation,
        );

        return $quota;
    }

    /**
     * Reducción por rendimientos del trabajo Ley 35/2006 art. 20.
     * Se aplica sobre el rendimiento neto previo (bruto - SS empleado).
     *
     * - Si rendimiento neto previo ≤ 14.852 €: reducción 6.498 €
     * - Si entre 14.852 € y 19.747,50 €: 6.498 - 1,14 × (rendimiento - 14.852)
     * - Si > 19.747,50 €: 0
     *
     * Importes vigentes 2023-2026 tras Ley 31/2022 (BOE-A-2022-22128).
     *
     * NOTA: La reducción no puede convertir el rendimiento neto en negativo.
     */
    private function workIncomeReduction(
        PayrollInput $input,
        Money $grossAnnual,
        Money $employeeSsTotal,
    ): Money {
        $previousNet = $grossAnnual->subtract($employeeSsTotal);

        $config = $this->repository->getParameter(
            $input->year,
            'irpf.reduccion_rendimientos_trabajo',
            RegionCode::state(),
        );

        if (! is_array($config)) {
            return Money::zero();
        }

        $minima = (float) ($config['minima'] ?? 0);
        $umbralMax = (float) ($config['umbral_max'] ?? 0);
        $umbralNeto = (float) ($config['umbral_neto'] ?? 0);

        if ($minima <= 0 || $umbralMax <= 0 || $umbralNeto <= 0) {
            return Money::zero();
        }

        $previousNetFloat = (float) $previousNet->amount;

        if ($previousNetFloat <= 0) {
            return Money::zero();
        }

        if ($previousNetFloat <= $umbralNeto) {
            $reduction = $minima;
        } elseif ($previousNetFloat <= $umbralMax) {
            $reduction = $minima - 1.14 * ($previousNetFloat - $umbralNeto);
        } else {
            $reduction = 0;
        }

        if ($reduction <= 0) {
            return Money::zero();
        }

        // No puede convertir el rendimiento neto en negativo.
        if ($reduction > $previousNetFloat) {
            $reduction = $previousNetFloat;
        }

        return Money::fromFloat($reduction);
    }

    private function effectiveRate(Money $gross, Money $tax): TaxRate
    {
        if ($gross->isZero()) {
            return TaxRate::zero();
        }

        $percentage = bcmul(bcdiv($tax->amount, $gross->amount, 8), '100', 4);

        return TaxRate::fromPercentage($percentage);
    }

    private function regionalLegalReference(RegionCode $region): string
    {
        return match ($region->code) {
            'MD' => 'https://www.bocm.es/',
            'CT' => 'https://dogc.gencat.cat/',
            'AN' => 'https://www.juntadeandalucia.es/boja/',
            'VC' => 'https://dogv.gva.es/',
            default => 'https://www.boe.es/',
        };
    }

    /**
     * Comprobamos que el bruto anual está por encima del SMI.
     * El SMI es el suelo legal mínimo (Ley 35/2014).
     */
    private function validateGrossAboveMinimum(PayrollInput $input): void
    {
        $smiMonthly = $this->repository->getParameter(
            $input->year,
            'ss.salario_minimo_interprofesional',
            RegionCode::state(),
        );

        if ($smiMonthly === null || ! is_numeric($smiMonthly)) {
            return; // si no está sembrado, no validamos (no inventar)
        }

        // SMI se publica como mensual en 14 pagas → anual = 14 × SMI
        $smiAnnual = (float) $smiMonthly * 14;

        if ((float) $input->grossAnnual->amount + 0.001 < $smiAnnual) {
            throw new RuntimeException(
                "El bruto anual ({$input->grossAnnual->amount} €) está por debajo del Salario Mínimo Interprofesional anual de {$input->year->year} ("
                .number_format($smiAnnual, 2, '.', '').' €). Esta calculadora sólo aplica a salarios iguales o superiores al SMI.',
            );
        }
    }
}
