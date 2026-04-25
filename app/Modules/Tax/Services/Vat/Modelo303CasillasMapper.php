<?php

namespace Modules\Tax\Services\Vat;

use Modules\Tax\ValueObjects\Money;

/**
 * Mapea los totales calculados de una autoliquidación a las casillas del
 * modelo 303 oficial.
 *
 * Cobertura MVP M7 (caso de uso régimen general operaciones interiores):
 *
 *   Sección "IVA devengado" (régimen general):
 *     - 01: base imponible al tipo general (4 %, 5 %, 10 %, 21 %).
 *           NOTA: el modelo 303 desdobla por tipo en filas 01-09.
 *           Aquí abrimos por tipo sólo si hay base en ese tipo.
 *     - 03: cuota IVA al tipo de la fila 01.
 *     - 04: base imponible al tipo reducido 10 %.
 *     - 06: cuota IVA al tipo 10 %.
 *     - 07: base al superreducido 4 %.
 *     - 09: cuota IVA al tipo 4 %.
 *     - 27: total cuota IVA devengado.
 *
 *   Recargo de Equivalencia (filas 16-26):
 *     - 16, 18, 20, 22, 24, 26 según tipo recargo.
 *
 *   Sección "IVA deducible":
 *     - 28: base operaciones interiores corrientes (compras).
 *     - 29: cuota IVA soportado deducible operaciones interiores corrientes.
 *     - 45: total a deducir (suma 29+31+33+35+37+39+41+43).
 *     - 46: diferencia (27 + 33 + ... − 45). En MVP simplificado = 27 − 29.
 *     - 71: resultado liquidación (positivo = a ingresar).
 *     - 72: cuotas a compensar de períodos anteriores.
 *     - 73: cuotas a devolver.
 *
 * Casillas NO cubiertas en MVP (se documentan vacías para no pretender
 * que se calculan):
 *   - 10-15: adquisiciones intracomunitarias y otras operaciones con ISP.
 *   - 30-44: regularizaciones, importaciones, bienes inversión, prorrata.
 *   - 50-67: información adicional, exportaciones, OSS/IOSS, prorrata.
 *   - 80-88: régimen simplificado (cubierto parcialmente vía simplifiedModulesData
 *     y exposición específica del calculator simplificado).
 *
 * Las claves se devuelven como strings de 2 dígitos ("01", "03", "27"…)
 * para alineamiento con la nomenclatura oficial.
 *
 * Fuente: Orden HFP/3666/2024, de 11 de diciembre, BOE 19-12-2024
 * (modelo 303 vigente desde 2025).
 */
class Modelo303CasillasMapper
{
    /**
     * Casillas del modelo 303 por tipo IVA general (filas devengado).
     * Cada tipo IVA tiene 3 columnas: base, %, cuota.
     *
     *   Tipo 4 % (superreducido): base 07, %, cuota 09
     *   Tipo 5 % (especial):      base 152, %, cuota 153 (nuevas filas 2023+)
     *   Tipo 10 % (reducido):     base 04, %, cuota 06
     *   Tipo 21 % (general):      base 01, %, cuota 03
     *
     * @var array<string, array{base: string, cuota: string}>
     */
    private const VAT_RATE_CASILLAS = [
        '4.0000' => ['base' => '07', 'cuota' => '09'],
        '5.0000' => ['base' => '152', 'cuota' => '153'],
        '10.0000' => ['base' => '04', 'cuota' => '06'],
        '21.0000' => ['base' => '01', 'cuota' => '03'],
    ];

    /**
     * Casillas del modelo 303 por tipo Recargo de Equivalencia.
     *
     *   Tipo 0,50 % (sobre 4 %):  base 13, %, cuota 15
     *   Tipo 0,62 % (sobre 5 %):  base 155, %, cuota 156
     *   Tipo 1,40 % (sobre 10 %): base 16, %, cuota 18
     *   Tipo 5,20 % (sobre 21 %): base 19, %, cuota 21 (anteriormente 22-24)
     *
     * @var array<string, array{base: string, cuota: string}>
     */
    private const SURCHARGE_CASILLAS = [
        '0.5000' => ['base' => '13', 'cuota' => '15'],
        '0.6200' => ['base' => '155', 'cuota' => '156'],
        '1.4000' => ['base' => '16', 'cuota' => '18'],
        '5.2000' => ['base' => '19', 'cuota' => '21'],
    ];

    /**
     * Construye el mapa casilla → Money para una autoliquidación.
     *
     * @param  array<string, array{base: Money, vat: Money}>  $vatBucketsByRate
     *                                                                           clave = TaxRate->percentage del tipo IVA, valor = base + cuota
     * @param  array<string, array{base: Money, vat: Money}>  $surchargeBucketsByRate
     *                                                                                 clave = TaxRate->percentage del recargo, valor = base + cuota
     * @return array<string, Money>
     */
    public function map(
        array $vatBucketsByRate,
        array $surchargeBucketsByRate,
        Money $totalVatAccrued,
        Money $totalDeductibleBase,
        Money $totalDeductibleAmount,
        Money $previousQuotaCarryForward,
        Money $liquidQuota,
        bool $isLastPeriod,
        bool $requestRefund = false,
    ): array {
        $casillas = [];

        // Sección IVA devengado: por tipo IVA en operaciones interiores.
        foreach ($vatBucketsByRate as $ratePercentage => $bucket) {
            if (! isset(self::VAT_RATE_CASILLAS[$ratePercentage])) {
                // Tipo no cubierto — se omite del mapping (queda en INFO).
                continue;
            }

            $cells = self::VAT_RATE_CASILLAS[$ratePercentage];
            $casillas[$cells['base']] = $bucket['base'];
            $casillas[$cells['cuota']] = $bucket['vat'];
        }

        // Recargo de equivalencia.
        foreach ($surchargeBucketsByRate as $ratePercentage => $bucket) {
            if (! isset(self::SURCHARGE_CASILLAS[$ratePercentage])) {
                continue;
            }

            $cells = self::SURCHARGE_CASILLAS[$ratePercentage];
            $casillas[$cells['base']] = $bucket['base'];
            $casillas[$cells['cuota']] = $bucket['vat'];
        }

        // Total cuota IVA devengado (casilla 27).
        $casillas['27'] = $totalVatAccrued;

        // IVA deducible operaciones interiores corrientes (casillas 28 y 29).
        if (! $totalDeductibleBase->isZero() || ! $totalDeductibleAmount->isZero()) {
            $casillas['28'] = $totalDeductibleBase;
            $casillas['29'] = $totalDeductibleAmount;
        }

        // Total a deducir (casilla 45) — en MVP = casilla 29.
        $casillas['45'] = $totalDeductibleAmount;

        // Diferencia (casilla 46) = 27 − 45 en MVP.
        $casillas['46'] = $totalVatAccrued->subtract($totalDeductibleAmount);

        // Compensación de periodos anteriores (casilla 78 — 2025+ formato
        // unificado). Históricamente fue 67/68/77 según versión orden.
        if (! $previousQuotaCarryForward->isZero()) {
            $casillas['78'] = $previousQuotaCarryForward;
        }

        // Resultado de la liquidación.
        // Casilla 69: resultado régimen general (= 46 − 78 en MVP).
        // Casilla 71: resultado liquidación (= 69 + ajustes; en MVP = 69).
        $resultRegimenGen = $casillas['46']->subtract($previousQuotaCarryForward);
        $casillas['69'] = $resultRegimenGen;
        $casillas['71'] = $resultRegimenGen;

        // Casillas 72/73: a compensar / a devolver.
        if ($liquidQuota->isNegative()) {
            // Cuota líquida negativa: o bien se pide devolución (último período)
            // o se traslada a períodos siguientes (a compensar).
            $absolute = new Money(ltrim($liquidQuota->amount, '-'), $liquidQuota->currency);
            if ($isLastPeriod && $requestRefund) {
                $casillas['73'] = $absolute;
            } else {
                $casillas['72'] = $absolute;
            }
        }

        return $casillas;
    }

    /**
     * Lista de casillas cubiertas y NO cubiertas, útil para documentar al
     * usuario qué se calcula en MVP M7.
     *
     * @return array{covered: list<string>, uncovered: list<string>}
     */
    public function coverage(): array
    {
        return [
            'covered' => [
                '01', '03', '04', '06', '07', '09', '152', '153', // IVA devengado por tipo
                '13', '15', '16', '18', '19', '21', '155', '156', // recargo equivalencia
                '27', // total cuota devengado
                '28', '29', '45', '46', // IVA deducible y diferencia
                '69', '71', '72', '73', '78', // resultado liquidación
            ],
            'uncovered' => [
                '10', '11', '12', '14', '17', '20', '22', '23', '24', '25', '26',
                '30', '31', '32', '33', '34', '35', '36', '37', '38', '39',
                '40', '41', '42', '43', '44',
                '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57',
                '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68',
                '70', '74', '75', '76', '77', '79',
                '80', '81', '82', '83', '84', '85', '86', '87', '88',
                '120', '122', '123', '124', '125', '126', '127', '128',
            ],
        ];
    }
}
