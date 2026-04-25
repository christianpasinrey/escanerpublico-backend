<?php

namespace Modules\Tax\Calculators\IncomeTax;

use InvalidArgumentException;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxInput;
use Modules\Tax\DTOs\IncomeTax\IncomeTaxResult;
use Modules\Tax\ValueObjects\RegionCode;
use RuntimeException;

/**
 * Dispatcher principal de la calculadora de IRPF anual (modelo 100).
 *
 * Selecciona la implementación específica según `input->regime->code`:
 *  - EDN              → EstimacionDirectaNormal
 *  - EDS              → EstimacionDirectaSimplificada
 *  - EO               → EstimacionObjetivaModulos
 *  - ASALARIADO_GEN   → RendimientosTrabajoSimulator
 *
 * Cualquier otro régimen IRPF (Beckham, AR atribución de rentas, etc.) se
 * rechaza con InvalidArgumentException porque está fuera de alcance MVP.
 *
 * Cobertura territorial: Estado + 4 CCAA top (MD, CT, AN, VC). Régimen foral
 * (PV, NC) se rechaza explícitamente.
 *
 * Fuente: Ley 35/2006 (BOE-A-2006-20764).
 */
class IncomeTaxCalculator
{
    public const SUPPORTED_REGIONS = ['MD', 'CT', 'AN', 'VC', RegionCode::STATE];

    public function __construct(
        private readonly EstimacionDirectaNormal $edn,
        private readonly EstimacionDirectaSimplificada $eds,
        private readonly EstimacionObjetivaModulos $eo,
        private readonly RendimientosTrabajoSimulator $asalariado,
    ) {}

    public function calculate(IncomeTaxInput $input): IncomeTaxResult
    {
        $this->validateRegion($input);

        return $this->resolveImplementation($input)->calculate($input);
    }

    private function resolveImplementation(IncomeTaxInput $input): IncomeTaxRegimeCalculator
    {
        return match ($input->regime->code) {
            'EDN' => $this->edn,
            'EDS' => $this->eds,
            'EO' => $this->eo,
            'ASALARIADO_GEN' => $this->asalariado,
            default => throw new InvalidArgumentException(
                "Régimen IRPF '{$input->regime->code}' fuera del alcance del MVP. ".
                'Soportados en M6: EDN, EDS, EO, ASALARIADO_GEN. '.
                'Régimen Beckham, foral PV/Navarra y atribución de rentas están fuera de alcance.',
            ),
        };
    }

    private function validateRegion(IncomeTaxInput $input): void
    {
        if ($input->region->isForal()) {
            throw new RuntimeException(
                'Régimen foral (País Vasco / Navarra) está fuera del alcance del MVP. '
                .'Estas comunidades disponen de su propio sistema fiscal.',
            );
        }

        if (! in_array($input->region->code, self::SUPPORTED_REGIONS, true)) {
            throw new RuntimeException(
                "La región {$input->region->name()} no está cubierta en MVP. "
                .'CCAA soportadas: Madrid, Cataluña, Andalucía y Comunidad Valenciana.',
            );
        }
    }
}
