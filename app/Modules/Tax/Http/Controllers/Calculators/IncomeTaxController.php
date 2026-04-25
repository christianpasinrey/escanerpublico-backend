<?php

namespace Modules\Tax\Http\Controllers\Calculators;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Tax\Calculators\IncomeTax\IncomeTaxCalculator;
use Modules\Tax\Http\Requests\IncomeTaxRequest;
use Modules\Tax\Http\Resources\IncomeTaxResource;
use RuntimeException;

/**
 * POST /api/v1/tax/income-tax — Calculadora de IRPF anual modelo 100.
 *
 * Stateless: no persiste nada en BD. La idempotencia es función de los inputs.
 * Los inputs no se cachean ni se loguean (PII: situación familiar, salario,
 * ingresos profesionales).
 *
 * Disclaimer: el cálculo es informativo. Cita BOE en cada concepto.
 */
class IncomeTaxController extends Controller
{
    public function __construct(
        private readonly IncomeTaxCalculator $calculator,
    ) {}

    public function __invoke(IncomeTaxRequest $request): JsonResponse
    {
        $input = $request->toIncomeTaxInput();

        try {
            $result = $this->calculator->calculate($input);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['income_tax' => [$e->getMessage()]],
            ], 422);
        }

        return IncomeTaxResource::make($result)
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
