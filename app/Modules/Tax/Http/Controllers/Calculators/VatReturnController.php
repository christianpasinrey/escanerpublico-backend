<?php

namespace Modules\Tax\Http\Controllers\Calculators;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Tax\Calculators\Vat\VatReturnCalculator;
use Modules\Tax\Http\Requests\VatReturnRequest;
use Modules\Tax\Http\Resources\VatReturnResource;

/**
 * POST /api/v1/tax/vat-return — Calculadora de autoliquidación IVA
 * (modelo 303 trimestral / modelo 390 anual).
 *
 * Stateless: no persiste nada. La idempotencia es función de los inputs.
 * Los inputs no se cachean ni loguean (PII en NIFs).
 *
 * Disclaimer: el cálculo es informativo. Cita BOE en cada concepto.
 */
class VatReturnController extends Controller
{
    public function __construct(
        private readonly VatReturnCalculator $calculator,
    ) {}

    public function __invoke(VatReturnRequest $request): JsonResponse
    {
        $input = $request->toVatReturnInput();

        try {
            $result = $this->calculator->calculate($input);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['vat_return' => [$e->getMessage()]],
            ], 422);
        }

        return VatReturnResource::make($result)
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
