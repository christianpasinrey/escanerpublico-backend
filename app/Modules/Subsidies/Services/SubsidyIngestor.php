<?php

namespace Modules\Subsidies\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\EntityResolver;
use Modules\Subsidies\Models\SubsidyCall;
use Modules\Subsidies\Models\SubsidyGrant;
use Modules\Subsidies\Models\SubsidyParseError;
use Modules\Subsidies\Models\SubsidySnapshot;

/**
 * Ingesta idempotente de payloads BDNS.
 *
 * Garantías:
 *  - Re-ingestar el mismo payload N veces produce 0 cambios y 0 snapshots nuevos.
 *  - Si el `content_hash` cambia (BDNS corrigió el dato), se actualiza la fila y
 *    se inserta un nuevo snapshot con el payload completo previo.
 *  - Las entidades relacionadas (Organization, Company) se reusan vía EntityResolver.
 */
class SubsidyIngestor
{
    public function __construct(
        private readonly EntityResolver $resolver,
        private readonly BeneficiarioParser $beneficiarioParser,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  un elemento del array `content` de BDNS
     * @return array{action: 'inserted'|'updated'|'skipped', model: SubsidyCall}
     */
    public function ingestCall(array $payload): array
    {
        $externalId = (int) ($payload['id'] ?? 0);
        if ($externalId === 0) {
            throw new \InvalidArgumentException('BDNS call payload missing id');
        }

        $hash = $this->hash($payload);
        $existing = SubsidyCall::where('source', 'BDNS')
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return ['action' => 'skipped', 'model' => $existing];
        }

        $orgId = $this->resolveOrganization($payload['nivel2'] ?? null, $payload['nivel3'] ?? null);

        $data = [
            'source' => 'BDNS',
            'external_id' => $externalId,
            'numero_convocatoria' => $payload['numeroConvocatoria'] ?? null,
            'organization_id' => $orgId,
            'description' => $payload['descripcion'] ?? null,
            'description_cooficial' => $payload['descripcionLeng'] ?? null,
            'reception_date' => $this->parseDate($payload['fechaRecepcion'] ?? null),
            'nivel1' => $payload['nivel1'] ?? null,
            'nivel2' => $payload['nivel2'] ?? null,
            'nivel3' => $payload['nivel3'] ?? null,
            'codigo_invente' => $payload['codigoInvente'] ?? null,
            'is_mrr' => (bool) ($payload['mrr'] ?? false),
            'content_hash' => $hash,
            'ingested_at' => now(),
        ];

        if ($existing === null) {
            $call = SubsidyCall::create($data);

            return ['action' => 'inserted', 'model' => $call];
        }

        $existing->fill($data);
        $existing->save();

        return ['action' => 'updated', 'model' => $existing];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{action: 'inserted'|'updated'|'skipped', model: SubsidyGrant}
     */
    public function ingestGrant(array $payload): array
    {
        $externalId = (int) ($payload['id'] ?? 0);
        if ($externalId === 0) {
            throw new \InvalidArgumentException('BDNS grant payload missing id');
        }

        $hash = $this->hash($payload);
        $existing = SubsidyGrant::where('source', 'BDNS')
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return ['action' => 'skipped', 'model' => $existing];
        }

        $beneficiarioRaw = isset($payload['beneficiario']) ? (string) $payload['beneficiario'] : null;
        [$nif, $name] = $this->beneficiarioParser->parse($beneficiarioRaw);

        if ($beneficiarioRaw !== null && $nif === null && $name !== null && $name === trim($beneficiarioRaw)) {
            // Patrón no reconocido — registrar parse_error pero seguir ingiriendo
            SubsidyParseError::create([
                'external_id' => $externalId,
                'source' => 'BDNS',
                'field' => 'beneficiario',
                'message' => 'Patrón NIF + nombre no reconocido',
                'raw_value' => $beneficiarioRaw,
            ]);
        }

        $companyId = $nif !== null
            ? $this->resolveCompany($nif, $name)
            : null;

        $orgId = $this->resolveOrganization($payload['nivel2'] ?? null, $payload['nivel3'] ?? null);

        $externalCallId = isset($payload['idConvocatoria']) ? (int) $payload['idConvocatoria'] : null;
        $callId = $externalCallId !== null
            ? SubsidyCall::where('source', 'BDNS')->where('external_id', $externalCallId)->value('id')
            : null;

        $data = [
            'source' => 'BDNS',
            'external_id' => $externalId,
            'cod_concesion' => $payload['codConcesion'] ?? null,
            'call_id' => $callId,
            'external_call_id' => $externalCallId,
            'organization_id' => $orgId,
            'company_id' => $companyId,
            'beneficiario_raw' => $beneficiarioRaw,
            'beneficiario_nif' => $nif,
            'beneficiario_name' => $name,
            'grant_date' => $this->parseDate($payload['fechaConcesion'] ?? null),
            'amount' => isset($payload['importe']) ? (float) $payload['importe'] : null,
            'ayuda_equivalente' => isset($payload['ayudaEquivalente']) ? (float) $payload['ayudaEquivalente'] : null,
            'instrumento' => isset($payload['instrumento']) ? trim((string) $payload['instrumento']) : null,
            'url_br' => $payload['urlBR'] ?? null,
            'tiene_proyecto' => (bool) ($payload['tieneProyecto'] ?? false),
            'id_persona' => isset($payload['idPersona']) ? (int) $payload['idPersona'] : null,
            'fecha_alta' => $this->parseDate($payload['fechaAlta'] ?? null),
            'content_hash' => $hash,
            'ingested_at' => now(),
        ];

        return DB::transaction(function () use ($existing, $data, $payload, $hash) {
            if ($existing === null) {
                $grant = SubsidyGrant::create($data);
                SubsidySnapshot::create([
                    'subsidy_grant_id' => $grant->id,
                    'raw_payload' => $payload,
                    'content_hash' => $hash,
                    'fetched_at' => now(),
                ]);

                return ['action' => 'inserted', 'model' => $grant];
            }

            // hash distinto → snapshot del estado nuevo + update
            $existing->fill($data);
            $existing->save();
            SubsidySnapshot::create([
                'subsidy_grant_id' => $existing->id,
                'raw_payload' => $payload,
                'content_hash' => $hash,
                'fetched_at' => now(),
            ]);

            return ['action' => 'updated', 'model' => $existing];
        });
    }

    /**
     * Resuelve organization por jerarquía nivel2 → nivel3. Si no existe, la crea
     * con identifier=NULL (BDNS no expone DIR3).
     */
    private function resolveOrganization(?string $nivel2, ?string $nivel3): ?int
    {
        $name = $nivel3 ?: $nivel2;
        if ($name === null || $name === '') {
            return null;
        }

        $existingId = $this->resolver->resolveOrganizationId(null, null, $name);
        if ($existingId !== null) {
            return $existingId;
        }

        $org = Organization::create([
            'name' => $name,
            'parent_name' => $nivel2 !== $nivel3 ? $nivel2 : null,
        ]);
        $this->resolver->registerOrganization($org);

        return $org->id;
    }

    private function resolveCompany(string $nif, ?string $name): int
    {
        $existingId = $this->resolver->resolveCompanyId($nif, $name);
        if ($existingId !== null) {
            return $existingId;
        }

        $company = Company::create([
            'nif' => $nif,
            'name' => $name,
        ]);
        $this->resolver->registerCompany($company);

        return $company->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        // Hash determinista: ordena las keys recursivamente.
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

    private function parseDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
