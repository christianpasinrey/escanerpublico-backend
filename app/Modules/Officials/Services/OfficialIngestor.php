<?php

namespace Modules\Officials\Services;

use Illuminate\Support\Facades\DB;
use Modules\Legislation\Models\BoeItem;
use Modules\Officials\Models\Appointment;
use Modules\Officials\Models\CargoExtractionError;
use Modules\Officials\Models\PublicOfficial;

/**
 * Procesa un BoeItem (sección II.A) y persiste el evento como appointment.
 *
 * Idempotente:
 *  - Misma persona (normalized_name) reutiliza la misma fila public_officials.
 *  - El unique key (public_official_id, boe_item_id, event_type) impide duplicados.
 */
class OfficialIngestor
{
    public function __construct(private readonly CargoExtractor $extractor) {}

    /**
     * @return array{result: 'extracted'|'skipped_collective'|'pattern_not_matched', appointment?: Appointment}
     */
    public function ingestFromBoeItem(BoeItem $item): array
    {
        $extracted = $this->extractor->extract($item->titulo);

        if ($extracted === null) {
            CargoExtractionError::updateOrCreate(
                ['boe_item_id' => $item->id],
                ['reason' => 'pattern_not_matched', 'raw_titulo' => $item->titulo]
            );

            return ['result' => 'pattern_not_matched'];
        }

        return DB::transaction(function () use ($item, $extracted) {
            // Si una iteración previa del extractor falló y dejó un error stale,
            // ahora lo limpiamos al obtener éxito.
            CargoExtractionError::where('boe_item_id', $item->id)->delete();

            $official = $this->resolveOfficial($extracted['full_name'], $extracted['honorific']);

            $appointment = Appointment::updateOrCreate(
                [
                    'public_official_id' => $official->id,
                    'boe_item_id' => $item->id,
                    'event_type' => $extracted['event_type'],
                ],
                [
                    'organization_id' => $item->organization_id,
                    'cargo' => $extracted['cargo'],
                    'effective_date' => $item->fecha_publicacion,
                ]
            );

            $this->refreshOfficialAggregates($official);

            return ['result' => 'extracted', 'appointment' => $appointment];
        });
    }

    private function resolveOfficial(string $fullName, ?string $honorific): PublicOfficial
    {
        $normalized = CargoExtractor::normalize($fullName);

        return PublicOfficial::firstOrCreate(
            ['normalized_name' => $normalized],
            ['full_name' => $fullName, 'honorific' => $honorific]
        );
    }

    private function refreshOfficialAggregates(PublicOfficial $official): void
    {
        $stats = Appointment::where('public_official_id', $official->id)
            ->selectRaw('COUNT(*) as cnt, MIN(effective_date) as first_date, MAX(effective_date) as last_date')
            ->first();

        $official->update([
            'appointments_count' => (int) ($stats->cnt ?? 0),
            'first_appointment_date' => $stats->first_date ?? null,
            'last_event_date' => $stats->last_date ?? null,
        ]);
    }
}
