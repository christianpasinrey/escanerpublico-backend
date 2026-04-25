<?php

namespace Modules\Tax\Http\Controllers\Calculators;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Tax\Calculators\FractionalPayment\FractionalPaymentCalculator;
use Modules\Tax\Http\Requests\FractionalPaymentRequest;
use Modules\Tax\Http\Resources\FractionalPaymentResource;

/**
 * POST /api/v1/tax/fractional-payment — Calculadora de pagos fraccionados
 * IRPF (modelos 130 y 131).
 *
 * Stateless: no persiste nada. La idempotencia es función de los inputs.
 * Los inputs no se cachean (PII en datos del contribuyente).
 *
 * Disclaimer: el cálculo es informativo. Cita BOE en cada concepto del
 * desglose. Los modelos 130/131 NUNCA son a devolver: el resultado se
 * trunca a 0,00 € si las deducciones superan el pago bruto.
 */
class FractionalPaymentController extends Controller
{
    public function __construct(
        private readonly FractionalPaymentCalculator $calculator,
    ) {}

    public function __invoke(FractionalPaymentRequest $request): JsonResponse
    {
        $input = $request->toFractionalPaymentInput();

        try {
            $result = $this->calculator->calculate($input);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['fractional_payment' => [$e->getMessage()]],
            ], 422);
        }

        return FractionalPaymentResource::make($result)
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
