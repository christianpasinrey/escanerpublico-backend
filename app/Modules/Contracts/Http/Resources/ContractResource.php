<?php

namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contracts\Models\Contract;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'expediente' => $this->expediente,
            'objeto' => $this->objeto,
            'status_code' => $this->status_code,
            'tipo_contrato_code' => $this->tipo_contrato_code,
            'importe_sin_iva' => $this->importe_sin_iva,
            'importe_con_iva' => $this->importe_con_iva,
            'valor_estimado' => $this->valor_estimado,
            'procedimiento_code' => $this->procedimiento_code,
            'nuts_code' => $this->nuts_code,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'snapshot_updated_at' => $this->snapshot_updated_at?->toIso8601String(),
            'annulled_at' => $this->annulled_at?->toIso8601String(),

            // includes
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'lots' => LotResource::collection($this->whenLoaded('lots')),
            'notices' => NoticeResource::collection($this->whenLoaded('notices')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'modifications' => ModificationResource::collection($this->whenLoaded('modifications')),
            'timeline' => $this->whenLoaded('notices', fn () => $this->buildTimeline()),
            'snapshots_summary' => SnapshotSummaryResource::collection($this->whenLoaded('snapshots')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(): array
    {
        $events = [];
        foreach ($this->notices as $n) {
            $events[] = [
                'type' => 'notice',
                'date' => $n->issue_date?->toDateString(),
                'code' => $n->notice_type_code,
                'title' => $this->noticeTitle($n->notice_type_code),
                'document_uri' => $n->document_uri,
            ];
        }
        if ($this->relationLoaded('modifications')) {
            foreach ($this->modifications as $m) {
                $events[] = [
                    'type' => $m->type,
                    'date' => $m->issue_date?->toDateString(),
                    'title' => ucfirst((string) $m->type),
                    'description' => $m->description,
                ];
            }
        }
        usort($events, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        return $events;
    }

    private function noticeTitle(string $code): string
    {
        return match ($code) {
            'DOC_PREV' => 'Anuncio previo',
            'DOC_CN' => 'Anuncio de licitación',
            'DOC_CD' => 'Pliegos publicados',
            'DOC_CAN_ADJ' => 'Adjudicación',
            'DOC_FORM' => 'Formalización',
            'DOC_MOD' => 'Modificación',
            'DOC_PRI' => 'Prórroga',
            'DOC_DES' => 'Desistimiento',
            'DOC_REN' => 'Renuncia',
            'DOC_ANUL' => 'Anulación',
            default => $code,
        };
    }
}
