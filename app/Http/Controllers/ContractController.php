<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contract::query();

        // Búsqueda texto libre
        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('objeto', 'like', "%{$q}%")
                  ->orWhere('organo_contratante', 'like', "%{$q}%")
                  ->orWhere('adjudicatario_nombre', 'like', "%{$q}%")
                  ->orWhere('expediente', 'like', "%{$q}%")
                  ->orWhere('adjudicatario_nif', 'like', "%{$q}%");
            });
        }

        // Filtros
        if ($status = $request->input('status')) {
            $query->status($status);
        }

        if ($tipo = $request->input('tipo')) {
            $query->tipo($tipo);
        }

        if ($procedimiento = $request->input('procedimiento')) {
            $query->procedimiento($procedimiento);
        }

        if ($importeMin = $request->input('importe_min')) {
            $query->importeMin((float) $importeMin);
        }

        if ($importeMax = $request->input('importe_max')) {
            $query->importeMax((float) $importeMax);
        }

        if ($ccaa = $request->input('ccaa')) {
            $query->where('comunidad_autonoma', $ccaa);
        }

        if ($organo = $request->input('organo')) {
            $query->where('organo_contratante', 'like', "%{$organo}%");
        }

        if ($adjudicatario = $request->input('adjudicatario')) {
            $query->where(function ($w) use ($adjudicatario) {
                $w->where('adjudicatario_nombre', 'like', "%{$adjudicatario}%")
                  ->orWhere('adjudicatario_nif', 'like', "%{$adjudicatario}%");
            });
        }

        if ($fechaDesde = $request->input('fecha_desde')) {
            $query->where('updated_at', '>=', $fechaDesde);
        }

        if ($fechaHasta = $request->input('fecha_hasta')) {
            $query->where('updated_at', '<=', $fechaHasta);
        }

        // Ordenación
        $sortField = $request->input('sort', 'updated_at');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['updated_at', 'importe_con_iva', 'fecha_adjudicacion', 'expediente'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $contracts = $query->paginate($request->input('per_page', 25));

        return response()->json($contracts);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json($contract);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total' => Contract::count(),
            'por_estado' => Contract::selectRaw('status_code, count(*) as total')
                ->groupBy('status_code')
                ->pluck('total', 'status_code'),
            'por_tipo' => Contract::selectRaw('tipo_contrato_code, count(*) as total')
                ->groupBy('tipo_contrato_code')
                ->pluck('total', 'tipo_contrato_code'),
            'importe_total' => Contract::sum('importe_con_iva'),
            'labels' => [
                'status' => Contract::STATUS_LABELS,
                'tipos' => Contract::TIPO_LABELS,
                'procedimientos' => Contract::PROCEDIMIENTO_LABELS,
            ],
        ]);
    }

    public function filters(): JsonResponse
    {
        return response()->json([
            'status' => Contract::STATUS_LABELS,
            'tipos' => Contract::TIPO_LABELS,
            'procedimientos' => Contract::PROCEDIMIENTO_LABELS,
            'comunidades' => Contract::whereNotNull('comunidad_autonoma')
                ->distinct()
                ->pluck('comunidad_autonoma')
                ->sort()
                ->values(),
        ]);
    }
}
