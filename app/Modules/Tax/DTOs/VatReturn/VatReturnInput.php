<?php

namespace Modules\Tax\DTOs\VatReturn;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Tax\ValueObjects\FiscalYear;
use Modules\Tax\ValueObjects\Money;
use Modules\Tax\ValueObjects\RegimeCode;

/**
 * Input completo para calcular una autoliquidación de IVA.
 *
 *  - regime: código IVA (IVA_GEN, IVA_CAJA, IVA_SIMPLE en MVP M7).
 *  - year: ejercicio fiscal.
 *  - quarter: 1..4 si es modelo 303 trimestral, null si es modelo 390 anual.
 *  - transactions: lista de operaciones del período.
 *  - previousQuotaCarryForward: cuotas a compensar acumuladas de
 *    autoliquidaciones anteriores (siempre se introduce como POSITIVO,
 *    el calculator lo restará al devengado − soportado para obtener
 *    la cuota líquida final).
 *  - simplifiedModulesData: estructura libre con módulos de Anexo II Orden
 *    anual del régimen simplificado (m², kW, personal asalariado…).
 *    Sólo aplica a IVA_SIMPLE.
 *
 * Reglas de validación de dominio:
 *   - regime debe ser de scope IVA y estar dentro del MVP M7.
 *   - quarter, si presente, en rango 1..4.
 *   - transactions no vacío.
 *   - previousQuotaCarryForward no negativo.
 *
 * Disclaimer legal: cálculo informativo, basado en legislación española
 * vigente del año `year`. No sustituye al asesoramiento profesional ni a
 * la presentación oficial AEAT.
 *
 * Fuente: Orden HFP/3666/2024 modelo 303; Orden HAC/819/2024 modelo 390;
 * Ley 37/1992 LIVA; RD 1624/1992 RIVA.
 */
final readonly class VatReturnInput implements JsonSerializable
{
    public const SUPPORTED_REGIMES = ['IVA_GEN', 'IVA_CAJA', 'IVA_SIMPLE'];

    /**
     * @param  list<VatTransactionInput>  $transactions
     * @param  array<string, mixed>|null  $simplifiedModulesData
     */
    public function __construct(
        public RegimeCode $regime,
        public FiscalYear $year,
        public array $transactions,
        public ?int $quarter = null,
        public Money $previousQuotaCarryForward = new Money('0.00'),
        public ?array $simplifiedModulesData = null,
    ) {
        if (! $regime->isIva()) {
            throw new InvalidArgumentException(
                "El régimen debe ser de scope 'iva', recibido '{$regime->scope()}'."
            );
        }

        if (! in_array($regime->code, self::SUPPORTED_REGIMES, true)) {
            throw new InvalidArgumentException(
                "Régimen IVA '{$regime->code}' fuera del alcance del MVP M7. ".
                'Soportados en M7: '.implode(', ', self::SUPPORTED_REGIMES).'. '.
                'Próximamente: REBU, REAGP, IVA_AAVV, IVA_OSS.'
            );
        }

        if ($quarter !== null && ($quarter < 1 || $quarter > 4)) {
            throw new InvalidArgumentException("Trimestre fuera de rango: {$quarter}");
        }

        if ($transactions === [] && $regime->code !== 'IVA_SIMPLE') {
            throw new InvalidArgumentException(
                'La autoliquidación debe contener al menos una transacción.'
            );
        }

        foreach ($transactions as $tx) {
            if (! $tx instanceof VatTransactionInput) {
                throw new InvalidArgumentException('Todas las transacciones deben ser VatTransactionInput.');
            }
        }

        if ($previousQuotaCarryForward->isNegative()) {
            throw new InvalidArgumentException(
                'La cuota a compensar de períodos anteriores no puede ser negativa.'
            );
        }

        if ($regime->code === 'IVA_SIMPLE' && $simplifiedModulesData === null) {
            throw new InvalidArgumentException(
                'El régimen simplificado requiere `simplifiedModulesData` con los módulos del Anexo II.'
            );
        }
    }

    public function isAnnual(): bool
    {
        return $this->quarter === null;
    }

    public function model(): string
    {
        return $this->isAnnual() ? '390' : '303';
    }

    public function periodLabel(): string
    {
        return $this->isAnnual()
            ? "Anual {$this->year->year}"
            : "{$this->quarter}T {$this->year->year}";
    }

    public function jsonSerialize(): array
    {
        return [
            'regime' => $this->regime,
            'year' => $this->year,
            'quarter' => $this->quarter,
            'transactions' => $this->transactions,
            'previous_quota_carry_forward' => $this->previousQuotaCarryForward,
            'simplified_modules_data' => $this->simplifiedModulesData,
            'is_annual' => $this->isAnnual(),
            'model' => $this->model(),
            'period_label' => $this->periodLabel(),
        ];
    }
}
