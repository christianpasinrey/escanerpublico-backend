<?php

namespace Modules\Contracts\Http\Controllers;

use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractNotice;
use Modules\Contracts\Models\Award;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContractController
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

        $contracts = $query->with('organization:id,name')->paginate($request->input('per_page', 25));
        Log::info("Consulta de contratos: {$contracts->total()} resultados para q={$q}, status={$status}, tipo={$tipo}, procedimiento={$procedimiento}, importe_min={$importeMin}, importe_max={$importeMax}, ccaa={$ccaa}, organo={$organo}, adjudicatario={$adjudicatario}, fecha_desde={$fechaDesde}, fecha_hasta={$fechaHasta}");
        return response()->json($contracts);
    }

    public function show(Contract $contract): JsonResponse
    {
        $contract->load([
            'organization.addresses.country',
            'organization.addresses.city',
            'organization.contacts',
            'awards.company',
            'notices',
            'documents',
        ]);

        return response()->json([
            'contract' => $contract,
            'timeline' => $this->buildTimeline($contract),
        ]);
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

    private function buildTimeline(Contract $contract): array
    {
        $events = [];

        foreach ($contract->notices as $notice) {
            if (!$notice->issue_date) continue;
            $events[] = [
                'date' => $notice->issue_date->toDateString(),
                'type' => $notice->notice_type_code,
                'label' => ContractNotice::NOTICE_TYPE_LABELS[$notice->notice_type_code] ?? $notice->notice_type_code,
                'status' => match ($notice->notice_type_code) {
                    'DOC_CN' => 'PUB',
                    'DOC_CAN_ADJ' => 'ADJ',
                    'DOC_FORM' => 'RES',
                    default => null,
                },
                'document_uri' => $notice->document_uri,
                'document_filename' => $notice->document_filename,
            ];
        }

        if ($contract->fecha_presentacion_limite) {
            $events[] = [
                'date' => $contract->fecha_presentacion_limite->toDateString(),
                'type' => 'DEADLINE', 'label' => 'Fin plazo presentación',
                'status' => 'EV', 'document_uri' => null, 'document_filename' => null,
            ];
        }

        $award = $contract->awards->first();

        if ($award?->award_date) {
            $hasAwardNotice = collect($events)->contains(fn($e) => $e['type'] === 'DOC_CAN_ADJ');
            if (!$hasAwardNotice) {
                $events[] = [
                    'date' => $award->award_date->toDateString(),
                    'type' => 'AWARD', 'label' => 'Adjudicación',
                    'status' => 'ADJ', 'document_uri' => null, 'document_filename' => null,
                ];
            }
        }

        if ($award?->formalization_date) {
            $hasFormNotice = collect($events)->contains(fn($e) => $e['type'] === 'DOC_FORM');
            if (!$hasFormNotice) {
                $events[] = [
                    'date' => $award->formalization_date->toDateString(),
                    'type' => 'FORMALIZATION', 'label' => 'Formalización',
                    'status' => 'RES', 'document_uri' => null, 'document_filename' => null,
                ];
            }
        }

        usort($events, fn($a, $b) => $a['date'] <=> $b['date']);
        return $events;
    }
}
