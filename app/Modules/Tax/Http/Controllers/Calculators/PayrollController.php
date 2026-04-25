<?php

namespace Modules\Tax\Http\Controllers\Calculators;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tax\Calculators\Payroll\PayrollCalculator;
use Modules\Tax\Http\Requests\PayrollRequest;
use Modules\Tax\Http\Resources\PayrollResource;
use RuntimeException;

/**
 * Endpoint de calculadora de nómina (POST /api/v1/tax/payroll).
 *
 * Stateless: no persiste inputs ni resultados (PII). Throttle compartido
 * con el resto del módulo via middleware.
 *
 * Devuelve un Breakdown completo línea a línea con referencia legal al BOE.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
    ) {}

    public function __invoke(PayrollRequest $request): JsonResponse
    {
        $input = $request->toPayrollInput();

        try {
            $result = $this->calculator->calculate($input);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'gross_annual' => [$e->getMessage()],
                ],
            ], 422);
        }

        return PayrollResource::make($result)->response();
    }
}
