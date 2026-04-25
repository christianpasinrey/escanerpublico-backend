<?php

namespace Modules\Tax\Http\Controllers\Calculators;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\Tax\Calculators\Invoice\InvoiceCalculator;
use Modules\Tax\Http\Requests\InvoiceRequest;
use Modules\Tax\Http\Resources\InvoiceResource;

/**
 * POST /api/v1/tax/invoice — Calculadora de factura para autónomo.
 *
 * Stateless: no persiste nada en BD. La idempotencia es función de los
 * inputs. Los inputs no se cachean ni se loguean (PII).
 *
 * Disclaimer: el cálculo es informativo. Cita BOE en cada concepto.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
    ) {}

    public function __invoke(InvoiceRequest $request): JsonResponse
    {
        $input = $request->toInvoiceInput();

        try {
            $result = $this->calculator->calculate($input);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['invoice' => [$e->getMessage()]],
            ], 422);
        }

        return InvoiceResource::make($result)
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
