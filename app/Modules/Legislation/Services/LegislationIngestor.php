<?php

namespace Modules\Legislation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\EntityResolver;
use Modules\Legislation\Models\BoeItem;
use Modules\Legislation\Models\BoeSummary;
use Modules\Legislation\Models\LegislationNorm;

/**
 * Ingesta idempotente de payloads BOE.
 *
 * Soporta:
 *  - Sumarios diarios completos (con sus items anidados en una transacción)
 *  - Normas consolidadas individuales
 *
 * Patrón: hash(payload) → SKIP si coincide, INSERT si no existe, UPDATE+nuevo registro si cambió.
 */
class LegislationIngestor
{
    public function __construct(private readonly EntityResolver $resolver) {}

    /**
     * Ingesta un sumario diario completo (con todos sus items en cascada).
     *
     * @param  array<string, mixed>  $summaryData  el campo `sumario` del response
     * @param  string  $fechaYmd  YYYY-MM-DD
     * @return array{summary: BoeSummary, items_inserted: int, items_updated: int, items_skipped: int}
     */
    public function ingestDailySummary(array $summaryData, string $fechaYmd): array
    {
        $diarios = $summaryData['diario'] ?? [];
        if (! is_array($diarios) || empty($diarios)) {
            throw new \InvalidArgumentException('Summary payload missing diario[]');
        }

        // BOE puede publicar varios diarios el mismo día (BOE-S-... vs BOE-A-...). Tomamos el primero.
        $diario = $diarios[0];
        $sumarioDiario = $diario['sumario_diario'] ?? null;
        $identificador = $sumarioDiario['identificador'] ?? null;
        if (! is_string($identificador) || $identificador === '') {
            throw new \InvalidArgumentException('Summary missing identificador');
        }

        $hash = $this->hash($summaryData);
        $existing = BoeSummary::where('source', 'BOE')
            ->where('identificador', $identificador)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return ['summary' => $existing, 'items_inserted' => 0, 'items_updated' => 0, 'items_skipped' => 0];
        }

        $summaryAttrs = [
            'source' => 'BOE',
            'identificador' => $identificador,
            'fecha_publicacion' => $fechaYmd,
            'numero' => $diario['numero'] ?? null,
            'url_pdf' => $sumarioDiario['url_pdf']['texto'] ?? null,
            'pdf_size_bytes' => isset($sumarioDiario['url_pdf']['szBytes']) ? (int) $sumarioDiario['url_pdf']['szBytes'] : null,
            'raw_payload' => $summaryData,
            'content_hash' => $hash,
            'ingested_at' => now(),
        ];

        return DB::transaction(function () use ($existing, $summaryAttrs, $diario, $fechaYmd) {
            if ($existing === null) {
                $summary = BoeSummary::create($summaryAttrs);
            } else {
                $existing->fill($summaryAttrs);
                $existing->save();
                $summary = $existing;
            }

            $stats = ['items_inserted' => 0, 'items_updated' => 0, 'items_skipped' => 0];
            foreach ($this->extractItems($diario, $fechaYmd) as $itemAttrs) {
                $itemAttrs['summary_id'] = $summary->id;
                $action = $this->upsertItem($itemAttrs);
                $stats["items_{$action}"]++;
            }

            return ['summary' => $summary, ...$stats];
        });
    }

    /**
     * Extrae los items individuales de un diario (estructura jerárquica:
     * sección → departamento → epígrafe → item).
     *
     * @param  array<string, mixed>  $diario
     * @return iterable<array<string, mixed>>
     */
    private function extractItems(array $diario, string $fechaYmd): iterable
    {
        $secciones = $diario['seccion'] ?? [];
        if (! is_array($secciones)) {
            return;
        }
        foreach ($secciones as $seccion) {
            $depts = $seccion['departamento'] ?? [];
            // BOE puede serializar departamento como objeto único o array
            if (isset($depts['nombre'])) {
                $depts = [$depts];
            }
            foreach ($depts as $dept) {
                $epigrafes = $dept['epigrafe'] ?? [];
                if (isset($epigrafes['nombre'])) {
                    $epigrafes = [$epigrafes];
                }
                foreach ($epigrafes as $epigrafe) {
                    $items = $epigrafe['item'] ?? [];
                    if (isset($items['identificador'])) {
                        $items = [$items];
                    }
                    foreach ($items as $item) {
                        yield $this->mapItem($item, $seccion, $dept, $epigrafe, $fechaYmd);
                    }
                }
                // BOE a veces pone items directos sin epígrafe
                $directItems = $dept['item'] ?? [];
                if (isset($directItems['identificador'])) {
                    $directItems = [$directItems];
                }
                foreach ($directItems as $item) {
                    yield $this->mapItem($item, $seccion, $dept, null, $fechaYmd);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $seccion
     * @param  array<string, mixed>  $dept
     * @param  array<string, mixed>|null  $epigrafe
     * @return array<string, mixed>
     */
    private function mapItem(array $item, array $seccion, array $dept, ?array $epigrafe, string $fechaYmd): array
    {
        $orgId = $this->resolveOrganization($dept['nombre'] ?? null, $dept['codigo'] ?? null);

        return [
            'source' => 'BOE',
            'external_id' => $item['identificador'] ?? '',
            'control' => $item['control'] ?? null,
            'seccion_code' => $seccion['codigo'] ?? null,
            'seccion_nombre' => $seccion['nombre'] ?? null,
            'organization_id' => $orgId,
            'departamento_code' => $dept['codigo'] ?? null,
            'departamento_nombre' => $dept['nombre'] ?? null,
            'epigrafe' => $epigrafe['nombre'] ?? null,
            'titulo' => $item['titulo'] ?? null,
            'url_pdf' => $item['url_pdf']['texto'] ?? null,
            'pdf_size_bytes' => isset($item['url_pdf']['szBytes']) ? (int) $item['url_pdf']['szBytes'] : null,
            'pagina_inicial' => $item['url_pdf']['pagina_inicial'] ?? null,
            'pagina_final' => $item['url_pdf']['pagina_final'] ?? null,
            'url_html' => $item['url_html'] ?? null,
            'url_xml' => $item['url_xml'] ?? null,
            'fecha_publicacion' => $fechaYmd,
            'content_hash' => $this->hash($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return 'inserted'|'updated'|'skipped'
     */
    private function upsertItem(array $attrs): string
    {
        if (! is_string($attrs['external_id']) || $attrs['external_id'] === '') {
            return 'skipped';
        }

        $existing = BoeItem::where('source', 'BOE')
            ->where('external_id', $attrs['external_id'])
            ->first();

        if ($existing !== null && $existing->content_hash === $attrs['content_hash']) {
            return 'skipped';
        }

        if ($existing === null) {
            BoeItem::create($attrs);

            return 'inserted';
        }

        $existing->fill($attrs);
        $existing->save();

        return 'updated';
    }

    /**
     * Ingesta una norma consolidada individual.
     *
     * @param  array<string, mixed>  $payload
     * @return array{action: 'inserted'|'updated'|'skipped', model: LegislationNorm}
     */
    public function ingestConsolidatedNorm(array $payload): array
    {
        $externalId = $payload['identificador'] ?? null;
        if (! is_string($externalId) || $externalId === '') {
            throw new \InvalidArgumentException('Consolidated norm payload missing identificador');
        }

        $hash = $this->hash($payload);
        $existing = LegislationNorm::where('source', 'BOE')
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return ['action' => 'skipped', 'model' => $existing];
        }

        $orgId = $this->resolveOrganization(
            $payload['departamento']['texto'] ?? null,
            $payload['departamento']['codigo'] ?? null
        );

        $data = [
            'source' => 'BOE',
            'external_id' => $externalId,
            'ambito_code' => $payload['ambito']['codigo'] ?? null,
            'ambito_text' => $payload['ambito']['texto'] ?? null,
            'organization_id' => $orgId,
            'departamento_code' => $payload['departamento']['codigo'] ?? null,
            'departamento_text' => $payload['departamento']['texto'] ?? null,
            'rango_code' => $payload['rango']['codigo'] ?? null,
            'rango_text' => $payload['rango']['texto'] ?? null,
            'numero_oficial' => $payload['numero_oficial'] ?? null,
            'titulo' => $payload['titulo'] ?? null,
            'fecha_disposicion' => $this->parseBoeDate($payload['fecha_disposicion'] ?? null),
            'fecha_publicacion' => $this->parseBoeDate($payload['fecha_publicacion'] ?? null),
            'fecha_vigencia' => $this->parseBoeDate($payload['fecha_vigencia'] ?? null),
            'fecha_actualizacion' => $this->parseBoeDateTime($payload['fecha_actualizacion'] ?? null),
            'vigencia_agotada' => ($payload['vigencia_agotada'] ?? 'N') === 'S',
            'estado_consolidacion_code' => $payload['estado_consolidacion']['codigo'] ?? null,
            'estado_consolidacion_text' => $payload['estado_consolidacion']['texto'] ?? null,
            'url_eli' => $payload['url_eli'] ?? null,
            'url_html_consolidada' => $payload['url_html_consolidada'] ?? null,
            'content_hash' => $hash,
            'ingested_at' => now(),
        ];

        if ($existing === null) {
            $norm = LegislationNorm::create($data);

            return ['action' => 'inserted', 'model' => $norm];
        }

        $existing->fill($data);
        $existing->save();

        return ['action' => 'updated', 'model' => $existing];
    }

    private function resolveOrganization(?string $name, ?string $code): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }
        $existingId = $this->resolver->resolveOrganizationId($code, null, $name);
        if ($existingId !== null) {
            return $existingId;
        }
        $org = Organization::create([
            'name' => $name,
            'identifier' => $code,
        ]);
        $this->resolver->registerOrganization($org);

        return $org->id;
    }

    private function parseBoeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // BOE usa "YYYYMMDD" en muchos campos. Soportamos también ISO YYYY-MM-DD.
        if (preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4).'-'.substr($value, 4, 2).'-'.substr($value, 6, 2);
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBoeDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // BOE usa "YYYYMMDDTHHMMSSZ"
        if (preg_match('/^(\d{8})T(\d{2})(\d{2})(\d{2})Z$/', $value, $m)) {
            $date = substr($m[1], 0, 4).'-'.substr($m[1], 4, 2).'-'.substr($m[1], 6, 2);

            return "{$date} {$m[2]}:{$m[3]}:{$m[4]}";
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        $sorted = $this->sortRecursively($payload);

        return hash('sha256', json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<int|string, mixed>  $arr
     * @return array<int|string, mixed>
     */
    private function sortRecursively(array $arr): array
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->sortRecursively($v);
            }
        }

        return $arr;
    }
}
