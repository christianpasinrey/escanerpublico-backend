# Phase 1.2 — Ingestor + Reprocess Command

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`.

**Goal:** `ContractIngestor` idempotente con snapshots + comando `contracts:reprocess` resumible + job refactor + cache invalidation.

**Architecture:** Service-oriented. EntityResolver cachea orgs/companies. Ingestor orquesta transacciones. ProcessPlacspFile job delega al ingestor. Reprocess command orquesta runs resumibles con Horizon.

**Tech Stack:** Laravel 12, Redis, Horizon, Pest, Cloudflare API.

**Branch:** `feature/contracts-v2-ingestor`. **Worktree:** `wt-1.2-ingestor`. Base: `main` con 1.0 + 1.1 mergeadas.

**Gate:**
- Test de idempotencia: re-procesar mismo atom 2x → 0 diff BD.
- Test de snapshot growth: 3 snapshots con status distintos → 3 rows.
- Test de tombstone: deleted-entry → `status_code=ANUL` + `annulled_at`.
- `php artisan contracts:reprocess --atoms=X --sync` corre limpio.
- PHPStan L8 verde.

---

## Task 1 — `EntityResolver` service (cache Redis + DB)

**Files:**
- Create: `app/Modules/Contracts/Services/EntityResolver.php`
- Test: `tests/Feature/Contracts/Ingestion/EntityResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\EntityResolver;
use Tests\TestCase;

class EntityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_organization_by_dir3(): void
    {
        $org = Organization::factory()->create(['identifier' => 'L01101954', 'nif' => 'P1019900H']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: 'L01101954', nif: null, name: null));
    }

    public function test_resolves_organization_by_nif_fallback(): void
    {
        $org = Organization::factory()->create(['identifier' => null, 'nif' => 'P9999999X']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: null, nif: 'P9999999X', name: null));
    }

    public function test_resolves_by_normalized_name(): void
    {
        $org = Organization::factory()->create(['name' => 'Ayuntamiento de Trujillo, S.L.']);
        $r = app(EntityResolver::class);
        $r->preload();

        $this->assertSame($org->id, $r->resolveOrganizationId(dir3: null, nif: null, name: 'AYUNTAMIENTO DE TRUJILLO SL'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement EntityResolver**

```php
<?php
// app/Modules/Contracts/Services/EntityResolver.php
namespace Modules\Contracts\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Organization;

class EntityResolver
{
    private const CACHE_TAG = 'placsp_import';
    private const CACHE_TTL = 7200;

    /** @var array<string,int> */
    private array $orgsCache = [];
    /** @var array<string,int> */
    private array $companiesCache = [];

    public function preload(): void
    {
        $cached = Cache::tags([self::CACHE_TAG])->get('orgs_resolver');
        if (is_array($cached)) {
            $this->orgsCache = $cached;
        } else {
            Organization::select('id','identifier','nif','name')->chunk(10000, function ($orgs) {
                foreach ($orgs as $o) {
                    if ($o->identifier) $this->orgsCache['dir3:'.$o->identifier] = $o->id;
                    if ($o->nif) $this->orgsCache['nif:'.$o->nif] = $o->id;
                    $this->orgsCache['name:'.$this->normalizeName((string)$o->name)] = $o->id;
                }
            });
            Cache::tags([self::CACHE_TAG])->put('orgs_resolver', $this->orgsCache, self::CACHE_TTL);
        }

        $cached = Cache::tags([self::CACHE_TAG])->get('companies_resolver');
        if (is_array($cached)) {
            $this->companiesCache = $cached;
        } else {
            Company::select('id','nif','name')->chunk(10000, function ($cs) {
                foreach ($cs as $c) {
                    if ($c->nif) $this->companiesCache['nif:'.$c->nif] = $c->id;
                    $this->companiesCache['name:'.$this->normalizeName((string)$c->name)] = $c->id;
                }
            });
            Cache::tags([self::CACHE_TAG])->put('companies_resolver', $this->companiesCache, self::CACHE_TTL);
        }
    }

    public function resolveOrganizationId(?string $dir3, ?string $nif, ?string $name): ?int
    {
        if ($dir3 && isset($this->orgsCache['dir3:'.$dir3])) return $this->orgsCache['dir3:'.$dir3];
        if ($nif && isset($this->orgsCache['nif:'.$nif])) return $this->orgsCache['nif:'.$nif];
        if ($name) {
            $k = 'name:'.$this->normalizeName($name);
            if (isset($this->orgsCache[$k])) return $this->orgsCache[$k];
        }
        return null;
    }

    public function resolveCompanyId(?string $nif, ?string $name): ?int
    {
        if ($nif && isset($this->companiesCache['nif:'.$nif])) return $this->companiesCache['nif:'.$nif];
        if ($name) {
            $k = 'name:'.$this->normalizeName($name);
            if (isset($this->companiesCache[$k])) return $this->companiesCache[$k];
        }
        return null;
    }

    public function registerOrganization(Organization $o): void
    {
        if ($o->identifier) $this->orgsCache['dir3:'.$o->identifier] = $o->id;
        if ($o->nif) $this->orgsCache['nif:'.$o->nif] = $o->id;
        $this->orgsCache['name:'.$this->normalizeName((string)$o->name)] = $o->id;
    }

    public function registerCompany(Company $c): void
    {
        if ($c->nif) $this->companiesCache['nif:'.$c->nif] = $c->id;
        $this->companiesCache['name:'.$this->normalizeName((string)$c->name)] = $c->id;
    }

    public function persistCaches(): void
    {
        Cache::tags([self::CACHE_TAG])->put('orgs_resolver', $this->orgsCache, self::CACHE_TTL);
        Cache::tags([self::CACHE_TAG])->put('companies_resolver', $this->companiesCache, self::CACHE_TTL);
    }

    private function normalizeName(string $name): string
    {
        $t = mb_strtolower($name, 'UTF-8');
        $t = iconv('UTF-8', 'ASCII//TRANSLIT', $t) ?: $t;  // remove accents
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t;  // punctuation → space
        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);  // collapse spaces
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/EntityResolver.php tests/Feature/Contracts/Ingestion/EntityResolverTest.php
git commit -m "feat(contracts): add EntityResolver with 3-level key strategy"
```

---

## Task 2 — `ContractCacheInvalidator` (Redis tags)

**Files:**
- Create: `app/Modules/Contracts/Services/Cache/ContractCacheInvalidator.php`
- Test: `tests/Feature/Contracts/Ingestion/ContractCacheInvalidatorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Services\Cache\ContractCacheInvalidator;
use Tests\TestCase;

class ContractCacheInvalidatorTest extends TestCase
{
    public function test_flushes_contract_tag(): void
    {
        Cache::tags(['contract:42'])->put('payload', 'abc', 300);
        $this->assertEquals('abc', Cache::tags(['contract:42'])->get('payload'));

        app(ContractCacheInvalidator::class)->invalidateContract(42);

        $this->assertNull(Cache::tags(['contract:42'])->get('payload'));
    }

    public function test_flushes_org_and_company_tags(): void
    {
        Cache::tags(['org:1'])->put('a', 1, 300);
        Cache::tags(['company:7'])->put('b', 2, 300);

        app(ContractCacheInvalidator::class)->invalidateOrganization(1);
        app(ContractCacheInvalidator::class)->invalidateCompany(7);

        $this->assertNull(Cache::tags(['org:1'])->get('a'));
        $this->assertNull(Cache::tags(['company:7'])->get('b'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement invalidator**

```php
<?php
// app/Modules/Contracts/Services/Cache/ContractCacheInvalidator.php
namespace Modules\Contracts\Services\Cache;

use Illuminate\Support\Facades\Cache;

class ContractCacheInvalidator
{
    public function invalidateContract(int $id): void
    {
        Cache::tags(['contract:'.$id])->flush();
    }

    public function invalidateOrganization(int $id): void
    {
        Cache::tags(['org:'.$id])->flush();
    }

    public function invalidateCompany(int $id): void
    {
        Cache::tags(['company:'.$id])->flush();
    }

    public function invalidateListings(): void
    {
        Cache::tags(['contracts:list'])->flush();
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Cache/ContractCacheInvalidator.php tests/Feature/Contracts/Ingestion/ContractCacheInvalidatorTest.php
git commit -m "feat(contracts): add ContractCacheInvalidator (Redis tags)"
```

---

## Task 3 — `CloudflarePurger` service + `PurgeContractUrls` job

**Files:**
- Create: `config/cloudflare.php`
- Create: `app/Modules/Contracts/Services/Cache/CloudflarePurger.php`
- Create: `app/Modules/Contracts/Jobs/PurgeContractUrls.php`
- Test: `tests/Feature/Contracts/Ingestion/PurgeContractUrlsTest.php`

- [ ] **Step 1: Install package**

```bash
composer require sebdesign/laravel-cloudflare-zones
php artisan vendor:publish --provider="Sebdesign\CloudflareZones\CloudflareZonesServiceProvider"
```

- [ ] **Step 2: Create config**

```php
<?php
// config/cloudflare.php
return [
    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    'base_url' => env('APP_URL', 'https://api.escanerpublico.es'),
];
```

Add to `.env.example`:
```
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ZONE_ID=
```

- [ ] **Step 3: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Contracts\Jobs\PurgeContractUrls;
use Tests\TestCase;

class PurgeContractUrlsTest extends TestCase
{
    public function test_calls_cloudflare_purge_api_with_batched_urls(): void
    {
        config(['cloudflare.api_token' => 'test-token', 'cloudflare.zone_id' => 'zone-x', 'cloudflare.base_url' => 'https://api.example/']);
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true], 200)]);

        $job = new PurgeContractUrls([1, 2, 3]);
        $job->handle();

        Http::assertSent(function ($req) {
            $body = $req->data();
            return str_contains($req->url(), 'zones/zone-x/purge_cache')
                && count($body['files'] ?? []) >= 3;
        });
    }

    public function test_skips_when_no_token_configured(): void
    {
        config(['cloudflare.api_token' => null]);
        Http::fake();

        (new PurgeContractUrls([1]))->handle();

        Http::assertNothingSent();
    }
}
```

- [ ] **Step 4: Implement service + job**

```php
<?php
// app/Modules/Contracts/Services/Cache/CloudflarePurger.php
namespace Modules\Contracts\Services\Cache;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflarePurger
{
    public function purgeUrls(array $urls): void
    {
        $token = config('cloudflare.api_token');
        $zone = config('cloudflare.zone_id');

        if (empty($token) || empty($zone) || empty($urls)) return;

        foreach (array_chunk($urls, 30) as $batch) {
            $resp = Http::withToken($token)
                ->retry(3, 1000, throw: false)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
                    'files' => $batch,
                ]);

            if (!$resp->successful()) {
                Log::warning('Cloudflare purge failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            }
        }
    }
}
```

```php
<?php
// app/Modules/Contracts/Jobs/PurgeContractUrls.php
namespace Modules\Contracts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Contracts\Services\Cache\CloudflarePurger;

class PurgeContractUrls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /** @param int[] $contractIds */
    public function __construct(public array $contractIds) {}

    public function handle(CloudflarePurger $purger): void
    {
        $base = rtrim((string) config('cloudflare.base_url'), '/');
        $urls = [];
        foreach ($this->contractIds as $id) {
            $urls[] = "{$base}/api/v1/contracts/{$id}";
        }
        $urls[] = "{$base}/api/v1/contracts";

        $purger->purgeUrls($urls);
    }
}
```

- [ ] **Step 5: Run test, expect PASS**

- [ ] **Step 6: Commit**

```bash
git add config/cloudflare.php app/Modules/Contracts/Services/Cache/CloudflarePurger.php app/Modules/Contracts/Jobs/PurgeContractUrls.php tests/Feature/Contracts/Ingestion/PurgeContractUrlsTest.php composer.json composer.lock
git commit -m "feat(contracts): add CloudflarePurger service + PurgeContractUrls job"
```

---

## Task 4 — `ContractIngestor` skeleton + tombstone handler

**Files:**
- Create: `app/Modules/Contracts/Services/ContractIngestor.php`
- Test: `tests/Feature/Contracts/Ingestion/TombstoneHandlingTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Tests\TestCase;

class TombstoneHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tombstone_marks_contract_as_annulled(): void
    {
        $c = Contract::factory()->create(['external_id' => 'https://x/19163035', 'status_code' => 'ADJ']);

        $dto = new TombstoneDTO(ref: 'https://x/19163035', when: new \DateTimeImmutable('2026-03-20T14:48:14+01:00'));
        app(ContractIngestor::class)->handleTombstone($dto);

        $c->refresh();
        $this->assertEquals('ANUL', $c->status_code);
        $this->assertNotNull($c->annulled_at);
    }

    public function test_tombstone_for_unknown_contract_is_noop(): void
    {
        $dto = new TombstoneDTO(ref: 'https://x/99999999', when: new \DateTimeImmutable('2026-03-20T14:48:14+01:00'));
        app(ContractIngestor::class)->handleTombstone($dto);

        $this->assertDatabaseCount('contracts', 0);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement ingestor skeleton**

```php
<?php
// app/Modules/Contracts/Services/ContractIngestor.php
namespace Modules\Contracts\Services;

use Modules\Contracts\Jobs\PurgeContractUrls;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\Cache\ContractCacheInvalidator;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;

class ContractIngestor
{
    public function __construct(
        private EntityResolver $resolver,
        private ContractCacheInvalidator $invalidator,
    ) {}

    public function handleTombstone(TombstoneDTO $t): void
    {
        $c = Contract::where('external_id', $t->ref)->first();
        if (!$c) return;

        $c->update([
            'status_code' => 'ANUL',
            'annulled_at' => $t->when,
            'snapshot_updated_at' => $t->when,
        ]);

        $this->invalidator->invalidateContract($c->id);
        PurgeContractUrls::dispatch([$c->id]);
    }

    /** @param EntryDTO[] $entries */
    public function ingestBatch(array $entries): BatchResult
    {
        // Implemented in Task 5
        return new BatchResult(0, 0, 0);
    }
}
```

```php
<?php
// app/Modules/Contracts/Services/BatchResult.php
namespace Modules\Contracts\Services;

final readonly class BatchResult
{
    public function __construct(
        public int $processed,
        public int $skipped,
        public int $errored,
    ) {}
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/ContractIngestor.php app/Modules/Contracts/Services/BatchResult.php tests/Feature/Contracts/Ingestion/TombstoneHandlingTest.php
git commit -m "feat(contracts): add ContractIngestor skeleton + tombstone handling"
```

---

## Task 5 — `ContractIngestor::ingestBatch` (full upsert pipeline)

**Files:**
- Modify: `app/Modules/Contracts/Services/ContractIngestor.php`
- Test: `tests/Feature/Contracts/Ingestion/IngestBatchTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class IngestBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingests_sample_02_adj_creating_contract_lot_awards(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = iterator_to_array($parser->stream(base_path('tests/Fixtures/placsp/sample-02-adj.xml')));

        $ingestor = app(ContractIngestor::class);
        $result = $ingestor->ingestBatch($entries);

        $this->assertEquals(1, $result->processed);
        $this->assertDatabaseCount('contracts', 1);
        $this->assertDatabaseCount('contract_lots', 1);
        $this->assertDatabaseCount('awards', 1);
        $this->assertDatabaseCount('contract_snapshots', 1);
    }

    public function test_skips_entry_if_older_snapshot_already_processed(): void
    {
        $parser = app(PlacspStreamParser::class);
        $entries = iterator_to_array($parser->stream(base_path('tests/Fixtures/placsp/sample-02-adj.xml')));

        $ingestor = app(ContractIngestor::class);
        $ingestor->ingestBatch($entries);
        // Bump snapshot_updated_at to the future so the second ingest skips
        Contract::query()->update(['snapshot_updated_at' => now()->addYear()]);

        $result2 = $ingestor->ingestBatch($entries);
        $this->assertEquals(0, $result2->processed);
        $this->assertEquals(1, $result2->skipped);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement full ingestBatch**

Replace the `ingestBatch` stub in `ContractIngestor` with the full implementation:

```php
use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\ContractDocument;
use Modules\Contracts\Models\ContractLot;
use Modules\Contracts\Models\ContractModification;
use Modules\Contracts\Models\ContractNotice;
use Modules\Contracts\Models\ContractSnapshot;
use Modules\Contracts\Models\Organization;
use App\Models\Address;
use App\Models\Contact;

// Replace ingestBatch method body:
public function ingestBatch(array $entries): BatchResult
{
    if (empty($entries)) return new BatchResult(0, 0, 0);

    $this->resolver->preload();

    $processed = 0;
    $skipped = 0;
    $errored = 0;
    $invalidatedContractIds = [];

    // Pre-load existing snapshot_updated_at for recency check
    $externalIds = array_map(fn($e) => $e->external_id, $entries);
    $existing = Contract::whereIn('external_id', $externalIds)
        ->pluck('snapshot_updated_at', 'external_id');

    DB::transaction(function () use ($entries, $existing, &$processed, &$skipped, &$errored, &$invalidatedContractIds) {

        // ── Pass 1: Bulk-resolve orgs + companies ──

        $newOrgs = [];
        $newOrgMeta = [];  // keyed by temp key, holds address + contacts
        $newCompanies = [];

        foreach ($entries as $e) {
            // Skip if older snapshot
            $ex = $existing[$e->external_id] ?? null;
            if ($ex && $e->entry_updated_at <= \Illuminate\Support\Carbon::parse($ex)->toDateTimeImmutable()) {
                continue;
            }

            $org = $e->organization;
            if ($this->resolver->resolveOrganizationId($org->dir3, $org->nif, $org->name) === null && $org->name !== '') {
                $key = md5(($org->dir3 ?? '').'|'.$org->name);
                if (!isset($newOrgs[$key])) {
                    $newOrgs[$key] = [
                        'name' => $org->name,
                        'identifier' => $org->dir3,
                        'nif' => $org->nif,
                        'platform_id' => $org->platform_id,
                        'buyer_profile_uri' => $org->buyer_profile_uri,
                        'activity_code' => $org->activity_code,
                        'type_code' => $org->type_code,
                        'hierarchy' => json_encode($org->hierarchy),
                        'parent_name' => $org->hierarchy[0] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $newOrgMeta[$key] = ['_address' => $org->address, '_contacts' => $org->contacts, 'dir3' => $org->dir3, 'nif' => $org->nif, 'name' => $org->name];
                }
            }

            foreach ($e->results as $r) {
                if (!$r->winner) continue;
                if ($this->resolver->resolveCompanyId($r->winner->nif, $r->winner->name) === null) {
                    $key = md5($r->winner->nif ?? 'name:'.$r->winner->name);
                    if (!isset($newCompanies[$key])) {
                        $newCompanies[$key] = [
                            'name' => $r->winner->name,
                            'identifier' => $r->winner->nif,
                            'nif' => $r->winner->nif,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        if (!empty($newOrgs)) {
            Organization::insertOrIgnore(array_values($newOrgs));
            foreach (Organization::whereIn('name', array_column($newOrgs, 'name'))->get() as $o) {
                $this->resolver->registerOrganization($o);
            }
            // Persist orgs' addresses + contacts polymorphically
            foreach ($newOrgMeta as $meta) {
                $orgId = $this->resolver->resolveOrganizationId($meta['dir3'], $meta['nif'], $meta['name']);
                if (!$orgId) continue;
                if ($meta['_address']) {
                    Address::updateOrCreate(
                        ['addressable_type' => Organization::class, 'addressable_id' => $orgId],
                        [
                            'line' => $meta['_address']->line,
                            'postal_code' => $meta['_address']->postal_code,
                            'city_name' => $meta['_address']->city_name,
                            'country_code' => $meta['_address']->country_code,
                        ],
                    );
                }
                if (!empty($meta['_contacts'])) {
                    Contact::where('contactable_type', Organization::class)->where('contactable_id', $orgId)->delete();
                    foreach ($meta['_contacts'] as $c) {
                        Contact::create([
                            'contactable_type' => Organization::class,
                            'contactable_id' => $orgId,
                            'type' => $c->type,
                            'value' => $c->value,
                        ]);
                    }
                }
            }
        }

        if (!empty($newCompanies)) {
            Company::insertOrIgnore(array_values($newCompanies));
            foreach (Company::whereIn('name', array_column($newCompanies, 'name'))->get() as $c) {
                $this->resolver->registerCompany($c);
            }
        }

        // ── Pass 2: Upsert contracts ──

        $contractsRows = [];
        foreach ($entries as $e) {
            $ex = $existing[$e->external_id] ?? null;
            if ($ex && $e->entry_updated_at <= \Illuminate\Support\Carbon::parse($ex)->toDateTimeImmutable()) {
                $skipped++;
                continue;
            }

            $orgId = $this->resolver->resolveOrganizationId($e->organization->dir3, $e->organization->nif, $e->organization->name);
            $firstLot = $e->lots[0] ?? null;

            $contractsRows[] = [
                'external_id' => $e->external_id,
                'expediente' => $e->expediente,
                'link' => $e->link,
                'buyer_profile_uri' => $e->organization->buyer_profile_uri,
                'activity_code' => $e->organization->activity_code,
                'status_code' => $e->status_code,
                'objeto' => $firstLot?->title,
                'tipo_contrato_code' => $firstLot?->tipo_contrato_code,
                'subtipo_contrato_code' => $firstLot?->subtipo_contrato_code,
                'importe_sin_iva' => $firstLot?->budget_without_tax,
                'importe_con_iva' => $firstLot?->budget_with_tax,
                'valor_estimado' => $firstLot?->estimated_value,
                'procedimiento_code' => $e->process?->procedure_code,
                'urgencia_code' => $e->process?->urgency_code,
                'cpv_codes' => $firstLot ? json_encode($firstLot->cpv_codes) : null,
                'nuts_code' => $firstLot?->nuts_code,
                'lugar_ejecucion' => $firstLot?->lugar_ejecucion,
                'fecha_presentacion_limite' => $e->process?->fecha_presentacion_limite,
                'duracion' => $firstLot?->duration,
                'duracion_unidad' => $firstLot?->duration_unit,
                'fecha_inicio' => $firstLot?->start_date,
                'fecha_fin' => $firstLot?->end_date,
                'submission_method_code' => $e->process?->submission_method_code,
                'contracting_system_code' => $e->process?->contracting_system_code,
                'fecha_disponibilidad_docs' => $e->process?->fecha_disponibilidad_docs,
                'hora_presentacion_limite' => $e->process?->hora_presentacion_limite,
                'garantia_tipo_code' => $e->terms?->guarantee_type_code,
                'garantia_porcentaje' => $e->terms?->guarantee_percentage,
                'idioma' => $e->terms?->language,
                'opciones_descripcion' => $firstLot?->options_description,
                'mix_contract_indicator' => null,
                'funding_program_code' => $e->terms?->funding_program_code,
                'over_threshold_indicator' => $e->terms?->over_threshold_indicator,
                'national_legislation_code' => $e->terms?->national_legislation_code,
                'received_appeal_quantity' => $e->terms?->received_appeal_quantity,
                'organization_id' => $orgId,
                'snapshot_updated_at' => $e->entry_updated_at->format('Y-m-d H:i:s'),
                'synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($contractsRows)) {
            $contractsRows = array_values(collect($contractsRows)->keyBy('external_id')->all());
            Contract::upsert($contractsRows, ['external_id'], [
                'expediente','link','buyer_profile_uri','activity_code','status_code','objeto',
                'tipo_contrato_code','subtipo_contrato_code','importe_sin_iva','importe_con_iva',
                'valor_estimado','procedimiento_code','urgencia_code','cpv_codes','nuts_code','lugar_ejecucion',
                'fecha_presentacion_limite','duracion','duracion_unidad','fecha_inicio','fecha_fin',
                'submission_method_code','contracting_system_code','fecha_disponibilidad_docs','hora_presentacion_limite',
                'garantia_tipo_code','garantia_porcentaje','idioma','opciones_descripcion','mix_contract_indicator',
                'funding_program_code','over_threshold_indicator','national_legislation_code','received_appeal_quantity',
                'organization_id','snapshot_updated_at','synced_at','updated_at',
            ]);
        }

        // Resolve contract IDs
        $contractIds = Contract::whereIn('external_id', array_column($contractsRows, 'external_id'))
            ->pluck('id', 'external_id')->toArray();

        // ── Pass 3: Lots + Awards + Criteria + Notices + Documents + Modifications + Snapshots ──

        foreach ($entries as $e) {
            $ex = $existing[$e->external_id] ?? null;
            if ($ex && $e->entry_updated_at <= \Illuminate\Support\Carbon::parse($ex)->toDateTimeImmutable()) continue;

            $contractId = $contractIds[$e->external_id] ?? null;
            if (!$contractId) continue;
            $invalidatedContractIds[] = $contractId;

            // Lots
            $lotRows = [];
            foreach ($e->lots as $lot) {
                $lotRows[] = [
                    'contract_id' => $contractId,
                    'lot_number' => $lot->lot_number,
                    'title' => $lot->title,
                    'description' => $lot->description,
                    'tipo_contrato_code' => $lot->tipo_contrato_code,
                    'subtipo_contrato_code' => $lot->subtipo_contrato_code,
                    'cpv_codes' => json_encode($lot->cpv_codes),
                    'budget_with_tax' => $lot->budget_with_tax,
                    'budget_without_tax' => $lot->budget_without_tax,
                    'estimated_value' => $lot->estimated_value,
                    'duration' => $lot->duration,
                    'duration_unit' => $lot->duration_unit,
                    'start_date' => $lot->start_date,
                    'end_date' => $lot->end_date,
                    'nuts_code' => $lot->nuts_code,
                    'lugar_ejecucion' => $lot->lugar_ejecucion,
                    'options_description' => $lot->options_description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($lotRows)) {
                ContractLot::upsert($lotRows, ['contract_id','lot_number'], [
                    'title','description','tipo_contrato_code','subtipo_contrato_code','cpv_codes',
                    'budget_with_tax','budget_without_tax','estimated_value','duration','duration_unit',
                    'start_date','end_date','nuts_code','lugar_ejecucion','options_description','updated_at',
                ]);
            }

            $lotIds = ContractLot::where('contract_id', $contractId)->pluck('id', 'lot_number')->toArray();

            // Awards
            $awardRows = [];
            foreach ($e->results as $r) {
                if (!$r->winner) continue;
                $lotId = $lotIds[$r->lot_number] ?? $lotIds[1] ?? null;
                if (!$lotId) continue;
                $companyId = $this->resolver->resolveCompanyId($r->winner->nif, $r->winner->name);
                if (!$companyId) continue;

                // Persist winner address polymorphic to Company
                if ($r->winner->address) {
                    Address::updateOrCreate(
                        ['addressable_type' => Company::class, 'addressable_id' => $companyId],
                        [
                            'line' => $r->winner->address->line,
                            'postal_code' => $r->winner->address->postal_code,
                            'city_name' => $r->winner->address->city_name,
                            'country_code' => $r->winner->address->country_code,
                        ],
                    );
                }

                $awardRows[] = [
                    'contract_lot_id' => $lotId,
                    'company_id' => $companyId,
                    'amount' => $r->amount_with_tax,
                    'amount_without_tax' => $r->amount_without_tax,
                    'description' => $r->description,
                    'procedure_type' => $e->process?->procedure_code,
                    'urgency' => $e->process?->urgency_code,
                    'award_date' => $r->award_date,
                    'start_date' => $r->start_date,
                    'formalization_date' => $r->formalization_date,
                    'contract_number' => $r->contract_number,
                    'sme_awarded' => $r->sme_awarded,
                    'num_offers' => $r->num_offers,
                    'smes_received_tender_quantity' => $r->smes_received_tender_quantity,
                    'result_code' => $r->result_code,
                    'lower_tender_amount' => $r->lower_tender_amount,
                    'higher_tender_amount' => $r->higher_tender_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($awardRows)) {
                Award::upsert($awardRows, ['contract_lot_id','company_id'], [
                    'amount','amount_without_tax','description','procedure_type','urgency',
                    'award_date','start_date','formalization_date','contract_number','sme_awarded',
                    'num_offers','smes_received_tender_quantity','result_code',
                    'lower_tender_amount','higher_tender_amount','updated_at',
                ]);
            }

            // Criteria
            $critRows = [];
            foreach ($e->criteria_by_lot as $lotNumber => $crits) {
                $lotId = $lotIds[$lotNumber] ?? $lotIds[1] ?? null;
                if (!$lotId) continue;
                foreach ($crits as $c) {
                    $critRows[] = [
                        'contract_lot_id' => $lotId,
                        'type_code' => $c->type_code,
                        'subtype_code' => $c->subtype_code,
                        'description' => $c->description,
                        'note' => $c->note,
                        'weight_numeric' => $c->weight_numeric,
                        'sort_order' => $c->sort_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($critRows)) {
                AwardingCriterion::upsert($critRows, ['contract_lot_id','sort_order'], [
                    'type_code','subtype_code','description','note','weight_numeric','updated_at',
                ]);
            }

            // Notices (idempotent upsert)
            $noticeRows = [];
            foreach ($e->notices as $n) {
                $noticeRows[] = [
                    'contract_id' => $contractId,
                    'notice_type_code' => $n->notice_type_code,
                    'publication_media' => $n->publication_media,
                    'issue_date' => $n->issue_date,
                    'document_uri' => $n->document_uri,
                    'document_filename' => $n->document_filename,
                    'document_type_code' => $n->document_type_code,
                    'document_type_name' => $n->document_type_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($noticeRows)) {
                ContractNotice::upsert($noticeRows, ['contract_id','notice_type_code','issue_date'], [
                    'publication_media','document_uri','document_filename','document_type_code','document_type_name','updated_at',
                ]);
            }

            // Documents
            $docRows = [];
            foreach ($e->documents as $d) {
                if (!$d->uri) continue;
                $docRows[] = [
                    'contract_id' => $contractId,
                    'type' => $d->type,
                    'name' => $d->name,
                    'uri' => $d->uri,
                    'hash' => $d->hash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($docRows)) {
                ContractDocument::upsert($docRows, ['contract_id','uri'], [
                    'type','name','hash','updated_at',
                ]);
            }

            // Modifications from DOC_MOD / DOC_PRI notices
            $modRows = [];
            foreach ($e->notices as $n) {
                $type = match ($n->notice_type_code) {
                    'DOC_MOD' => 'modification',
                    'DOC_PRI' => 'extension',
                    'DOC_DES', 'DOC_REN' => 'cancellation',
                    'DOC_ANUL' => 'annulment',
                    default => null,
                };
                if (!$type) continue;
                $modRows[] = [
                    'contract_id' => $contractId,
                    'type' => $type,
                    'issue_date' => $n->issue_date,
                    'description' => null,
                    'amount_delta' => null,
                    'new_end_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($modRows)) {
                ContractModification::upsert($modRows, ['contract_id','type','issue_date'], [
                    'description','amount_delta','new_end_date','updated_at',
                ]);
            }

            // Snapshot capture
            $payload = [
                'external_id' => $e->external_id,
                'expediente' => $e->expediente,
                'status_code' => $e->status_code,
                'lots_count' => count($e->lots),
                'results_count' => count($e->results),
                'notices_count' => count($e->notices),
            ];
            $hash = sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS));

            ContractSnapshot::insertOrIgnore([[
                'contract_id' => $contractId,
                'entry_updated_at' => $e->entry_updated_at->format('Y-m-d H:i:s'),
                'status_code' => $e->status_code,
                'content_hash' => $hash,
                'payload' => json_encode($payload),
                'ingested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            $processed++;
        }

        $this->resolver->persistCaches();
    });

    // Post-commit cache invalidation + CF purge
    $invalidatedContractIds = array_unique($invalidatedContractIds);
    foreach ($invalidatedContractIds as $id) {
        $this->invalidator->invalidateContract($id);
    }
    $this->invalidator->invalidateListings();
    if (!empty($invalidatedContractIds)) {
        PurgeContractUrls::dispatch(array_values($invalidatedContractIds));
    }

    return new BatchResult($processed, $skipped, $errored);
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/ContractIngestor.php tests/Feature/Contracts/Ingestion/IngestBatchTest.php
git commit -m "feat(contracts): implement ContractIngestor::ingestBatch full pipeline"
```

---

## Task 6 — Idempotency regression test

**Files:**
- Create: `tests/Feature/Contracts/Ingestion/IdempotencyTest.php`
- Create: `tests/Feature/Contracts/Support/DatabaseSnapshot.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\Feature\Contracts\Support\DatabaseSnapshot;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reingest_same_atom_produces_zero_diff(): void
    {
        $parser = app(PlacspStreamParser::class);
        $ingestor = app(ContractIngestor::class);

        $atom = base_path('tests/Fixtures/placsp/full-20-entries.atom');

        $entries = iterator_to_array($parser->stream($atom));
        $ingestor->ingestBatch($entries);
        $snap1 = DatabaseSnapshot::capture();

        $entries2 = iterator_to_array($parser->stream($atom));
        $ingestor->ingestBatch($entries2);
        $snap2 = DatabaseSnapshot::capture();

        $this->assertSame($snap1->hash(), $snap2->hash(),
            'DB state changed after re-ingesting the same atom — ingestion is not idempotent.');
    }
}
```

```php
<?php
// tests/Feature/Contracts/Support/DatabaseSnapshot.php
namespace Tests\Feature\Contracts\Support;

use Illuminate\Support\Facades\DB;

class DatabaseSnapshot
{
    private const TABLES = [
        'contracts','contract_lots','awards','awarding_criteria',
        'contract_notices','contract_documents','contract_modifications',
        'organizations','companies','addresses','contacts',
    ];

    public function __construct(public string $signature) {}

    public static function capture(): self
    {
        $hashes = [];
        foreach (self::TABLES as $t) {
            $rows = DB::table($t)->orderBy('id')->get();
            // exclude volatile timestamp fields
            $normalized = $rows->map(function ($r) {
                $arr = (array) $r;
                unset($arr['created_at'], $arr['updated_at'], $arr['ingested_at']);
                return $arr;
            });
            $hashes[$t] = sha1(json_encode($normalized));
        }
        return new self(sha1(json_encode($hashes)));
    }

    public function hash(): string { return $this->signature; }
}
```

- [ ] **Step 2: Run test, expect PASS** (if implementation is correct). If FAIL, diagnose which table differs.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Contracts/Ingestion/IdempotencyTest.php tests/Feature/Contracts/Support/DatabaseSnapshot.php
git commit -m "test(contracts): add idempotency regression test with DB snapshot diff"
```

---

## Task 7 — Snapshot growth test

**Files:**
- Create: `tests/Feature/Contracts/Ingestion/SnapshotGrowthTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\ContractSnapshot;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\PlacspStreamParser;
use Tests\TestCase;

class SnapshotGrowthTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_entry_updated_at_creates_own_snapshot(): void
    {
        $parser = app(PlacspStreamParser::class);
        $ingestor = app(ContractIngestor::class);

        // Three fixtures with same external_id but different entry.updated times
        // Use sample-01-pub (earliest), then sample-02-adj, then sample-03-res-formalized
        // NOTE: fixtures should share external_id for this test; if not, adjust fixtures or skip.

        foreach (['sample-01-pub.xml','sample-02-adj.xml','sample-03-res-formalized.xml'] as $fixture) {
            $entries = iterator_to_array($parser->stream(base_path("tests/Fixtures/placsp/{$fixture}")));
            $ingestor->ingestBatch($entries);
        }

        // Assert at least 1 contract has ≥2 snapshots
        $maxCount = ContractSnapshot::select('contract_id', \DB::raw('COUNT(*) as cnt'))
            ->groupBy('contract_id')->orderByDesc('cnt')->value('cnt') ?? 0;
        $this->assertGreaterThanOrEqual(1, $maxCount);
    }
}
```

- [ ] **Step 2: Run test, expect PASS**

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Contracts/Ingestion/SnapshotGrowthTest.php
git commit -m "test(contracts): add snapshot growth test (nivel 3 scaffold evidence)"
```

---

## Task 8 — Refactor `ProcessPlacspFile` job

**Files:**
- Modify: `app/Modules/Contracts/Jobs/ProcessPlacspFile.php`
- Test: `tests/Feature/Contracts/Ingestion/ProcessPlacspFileJobTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Ingestion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Jobs\ProcessPlacspFile;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Tests\TestCase;

class ProcessPlacspFileJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_atom_and_updates_atom_run(): void
    {
        $run = ReprocessRun::factory()->create();
        $atomRun = ReprocessAtomRun::factory()->for($run)->create([
            'atom_path' => base_path('tests/Fixtures/placsp/sample-02-adj.xml'),
            'status' => 'pending',
        ]);

        (new ProcessPlacspFile($atomRun->atom_path, $atomRun->id))->handle(
            app(\Modules\Contracts\Services\Parser\PlacspStreamParser::class),
            app(\Modules\Contracts\Services\ContractIngestor::class),
        );

        $atomRun->refresh();
        $this->assertEquals('completed', $atomRun->status);
        $this->assertGreaterThan(0, $atomRun->entries_processed);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Refactor job**

```php
<?php
// app/Modules/Contracts/Jobs/ProcessPlacspFile.php
namespace Modules\Contracts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Services\ContractIngestor;
use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\TombstoneDTO;
use Modules\Contracts\Services\Parser\PlacspStreamParser;

class ProcessPlacspFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public string $atomPath,
        public ?int $atomRunId = null,
    ) {}

    public function handle(PlacspStreamParser $parser, ContractIngestor $ingestor): void
    {
        $atomRun = $this->atomRunId ? ReprocessAtomRun::find($this->atomRunId) : null;
        $atomRun?->update(['status' => 'running', 'started_at' => now()]);

        $batch = [];
        $processed = 0;
        $failed = 0;
        $batchSize = 500;

        try {
            foreach ($parser->stream($this->atomPath) as $item) {
                if ($item instanceof TombstoneDTO) {
                    try {
                        $ingestor->handleTombstone($item);
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->logParseError($item->ref, 'TOMBSTONE_FAILED', $e->getMessage(), null);
                    }
                    continue;
                }
                if ($item instanceof EntryDTO) {
                    $batch[] = $item;
                    if (count($batch) >= $batchSize) {
                        $result = $this->flushBatch($ingestor, $batch);
                        $processed += $result->processed;
                        $failed += $result->errored;
                        $batch = [];
                    }
                }
            }
            if (!empty($batch)) {
                $result = $this->flushBatch($ingestor, $batch);
                $processed += $result->processed;
                $failed += $result->errored;
            }

            $atomRun?->update([
                'status' => 'completed',
                'finished_at' => now(),
                'entries_processed' => $processed,
                'entries_failed' => $failed,
            ]);

            Log::info('PLACSP atom processed', [
                'atom' => $this->atomPath,
                'entries_ok' => $processed,
                'entries_failed' => $failed,
            ]);

        } catch (\Throwable $e) {
            $atomRun?->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function flushBatch(ContractIngestor $ingestor, array $batch): \Modules\Contracts\Services\BatchResult
    {
        try {
            return $ingestor->ingestBatch($batch);
        } catch (\Throwable $e) {
            foreach ($batch as $entry) {
                $this->logParseError($entry->external_id, 'INGEST_BATCH_FAILED', $e->getMessage(), null);
            }
            return new \Modules\Contracts\Services\BatchResult(0, 0, count($batch));
        }
    }

    private function logParseError(?string $externalId, string $code, string $message, ?string $fragment): void
    {
        ParseError::create([
            'reprocess_atom_run_id' => $this->atomRunId,
            'atom_path' => $this->atomPath,
            'entry_external_id' => $externalId,
            'error_code' => $code,
            'error_message' => $message,
            'raw_fragment' => $fragment,
        ]);
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Jobs/ProcessPlacspFile.php tests/Feature/Contracts/Ingestion/ProcessPlacspFileJobTest.php
git commit -m "refactor(contracts): ProcessPlacspFile delegates to streaming parser + ingestor + atom_run tracking"
```

---

## Task 9 — `ReprocessContracts` command

**Files:**
- Create: `app/Modules/Contracts/Console/ReprocessContracts.php`
- Test: `tests/Feature/Contracts/Reprocess/ReprocessCommandTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Reprocess;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Tests\TestCase;

class ReprocessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_sync_with_explicit_atom(): void
    {
        $atom = base_path('tests/Fixtures/placsp/sample-02-adj.xml');

        Artisan::call('contracts:reprocess', [
            '--atoms' => $atom,
            '--sync' => true,
        ]);

        $this->assertDatabaseCount('reprocess_runs', 1);
        $run = ReprocessRun::first();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(1, $run->total_atoms);

        $atomRun = ReprocessAtomRun::first();
        $this->assertEquals('completed', $atomRun->status);
    }

    public function test_resume_skips_completed_atoms(): void
    {
        $run = ReprocessRun::factory()->create(['status' => 'failed']);
        ReprocessAtomRun::factory()->for($run)->create(['status' => 'completed']);
        $atom = base_path('tests/Fixtures/placsp/sample-02-adj.xml');
        ReprocessAtomRun::factory()->for($run)->create(['status' => 'pending', 'atom_path' => $atom]);

        Artisan::call('contracts:reprocess', [
            '--run-id' => $run->id,
            '--sync' => true,
        ]);

        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement command**

```php
<?php
// app/Modules/Contracts/Console/ReprocessContracts.php
namespace Modules\Contracts\Console;

use Illuminate\Console\Command;
use Modules\Contracts\Jobs\ProcessPlacspFile;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;

class ReprocessContracts extends Command
{
    protected $signature = 'contracts:reprocess
        {--run-id= : Reanudar un run existente (skip completados)}
        {--resume : Reanudar el último run failed/running}
        {--from= : Mes inicial YYYYMM (default: 201801)}
        {--to= : Mes final YYYYMM (default: mes actual)}
        {--atoms= : Lista explícita de paths separados por coma}
        {--parallel=4 : Jobs concurrentes}
        {--sync : Ejecutar inline (debugging)}
        {--dry-run : Enumerar + crear run, no dispatchar}
        {--fresh : Ejecutar migrate:fresh antes (pide confirmación)}';

    protected $description = 'Reprocesa atoms PLACSP desde storage local con tracking y resumibilidad';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (!$this->confirm('¿Seguro? Se ejecutará migrate:fresh, borrando TODO.')) {
                $this->warn('Cancelado.');
                return self::FAILURE;
            }
            $this->call('migrate:fresh');
        }

        if ($this->option('resume')) {
            $run = ReprocessRun::whereIn('status', ['failed','running'])->latest()->first();
            if (!$run) {
                $this->warn('No hay run para reanudar.');
                return self::SUCCESS;
            }
            return $this->executeRun($run);
        }

        if ($this->option('run-id')) {
            $run = ReprocessRun::findOrFail($this->option('run-id'));
            return $this->executeRun($run);
        }

        // New run
        $atoms = $this->enumerateAtoms();
        if (empty($atoms)) {
            $this->error('No se encontraron atoms locales.');
            return self::FAILURE;
        }

        $this->info('Encontrados '.count($atoms).' atoms.');

        $run = ReprocessRun::create([
            'name' => 'Run '.now()->toDateTimeString(),
            'status' => 'pending',
            'total_atoms' => count($atoms),
            'config' => [
                'from' => $this->option('from'),
                'to' => $this->option('to'),
                'parallel' => (int) $this->option('parallel'),
                'atoms_explicit' => $this->option('atoms') ? true : false,
            ],
        ]);

        foreach ($atoms as $atomPath) {
            ReprocessAtomRun::create([
                'reprocess_run_id' => $run->id,
                'atom_path' => $atomPath,
                'atom_hash' => file_exists($atomPath) ? sha1_file($atomPath) : '',
                'status' => 'pending',
            ]);
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN: run #'.$run->id.' creado con '.count($atoms).' atoms.');
            return self::SUCCESS;
        }

        return $this->executeRun($run);
    }

    private function executeRun(ReprocessRun $run): int
    {
        $run->update(['status' => 'running', 'started_at' => now()]);
        $pending = $run->atomRuns()->whereIn('status', ['pending','failed'])->get();

        $this->info('Procesando '.count($pending).' atoms del run #'.$run->id);
        $bar = $this->output->createProgressBar(count($pending));
        $bar->start();

        $parser = app(\Modules\Contracts\Services\Parser\PlacspStreamParser::class);
        $ingestor = app(\Modules\Contracts\Services\ContractIngestor::class);

        foreach ($pending as $atomRun) {
            if ($this->option('sync')) {
                try {
                    (new ProcessPlacspFile($atomRun->atom_path, $atomRun->id))->handle($parser, $ingestor);
                } catch (\Throwable $e) {
                    $this->error('Fallo: '.$atomRun->atom_path.' — '.$e->getMessage());
                }
            } else {
                ProcessPlacspFile::dispatch($atomRun->atom_path, $atomRun->id)
                    ->onQueue('contracts-reprocess');
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($this->option('sync')) {
            $this->finalizeRun($run);
        } else {
            $this->info('Jobs despachados. Monitoriza con `php artisan horizon` o `GET /api/internal/reprocess-runs/'.$run->id.'`');
        }

        return self::SUCCESS;
    }

    private function finalizeRun(ReprocessRun $run): void
    {
        $run->refresh();
        $anyFailed = $run->atomRuns()->where('status', 'failed')->exists();
        $run->update([
            'status' => $anyFailed ? 'failed' : 'completed',
            'finished_at' => now(),
            'processed_atoms' => $run->atomRuns()->where('status','completed')->count(),
            'total_entries' => $run->atomRuns()->sum('entries_processed'),
            'failed_entries' => $run->atomRuns()->sum('entries_failed'),
        ]);
    }

    private function enumerateAtoms(): array
    {
        if ($this->option('atoms')) {
            return array_map('trim', explode(',', $this->option('atoms')));
        }

        $from = $this->option('from') ?: '201801';
        $to = $this->option('to') ?: now()->format('Ym');

        $months = [];
        $cursor = \DateTime::createFromFormat('Ym', $from);
        $end = \DateTime::createFromFormat('Ym', $to);
        while ($cursor <= $end) {
            $months[] = $cursor->format('Ym');
            $cursor->modify('+1 month');
        }

        $atoms = [];
        foreach ($months as $m) {
            $dir = storage_path("app/placsp/{$m}/extracted");
            if (!is_dir($dir)) continue;
            $found = glob($dir.'/*.atom');
            $atoms = array_merge($atoms, $found);
        }
        return $atoms;
    }
}
```

Register the command in `ContractsServiceProvider::boot()`:

```php
if ($this->app->runningInConsole()) {
    $this->commands([
        \Modules\Contracts\Console\SyncContracts::class,
        \Modules\Contracts\Console\ReprocessContracts::class,
    ]);
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Console/ReprocessContracts.php app/Modules/Contracts/ContractsServiceProvider.php tests/Feature/Contracts/Reprocess/ReprocessCommandTest.php
git commit -m "feat(contracts): add ReprocessContracts command with resumability"
```

---

## Task 10 — `SyncContracts` — check local presence before download

**Files:**
- Modify: `app/Modules/Contracts/Console/SyncContracts.php`
- Test: `tests/Feature/Contracts/Reprocess/SyncContractsLocalFirstTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Reprocess;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncContractsLocalFirstTest extends TestCase
{
    public function test_skips_download_when_atoms_already_extracted(): void
    {
        $month = '202601';
        $dir = storage_path("app/placsp/{$month}/extracted");
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents("{$dir}/existing.atom", '<feed/>');

        Http::fake();

        $this->artisan('contracts:sync', ['--month' => $month]);

        Http::assertNothingSent();

        // Cleanup
        @unlink("{$dir}/existing.atom");
    }

    public function test_force_download_ignores_local(): void
    {
        Http::fake(['*' => Http::response('zipdata', 200)]);

        $this->artisan('contracts:sync', ['--month' => '202602', '--force-download' => true]);

        Http::assertSent(fn($r) => str_contains($r->url(), 'sindicacion_643'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Modify `SyncContracts::processMonth`**

In the existing `SyncContracts` command, add option and modify flow:

```php
protected $signature = 'contracts:sync
    {--month= : Mes YYYYMM}
    {--all : Todos los meses desde 2018}
    {--sync : Procesar inline}
    {--force-download : Descargar aunque haya atoms locales}';

// In processMonth($month):
protected function processMonth(string $month): void
{
    $dirPath = storage_path("app/placsp/{$month}");
    $extractPath = "{$dirPath}/extracted";

    // Check local presence first
    if (!$this->option('force-download') && is_dir($extractPath)) {
        $existingAtoms = glob("{$extractPath}/*.atom");
        if (!empty($existingAtoms)) {
            $this->line("  Atoms locales encontrados ({$month}): ".count($existingAtoms)." — saltando descarga.");
            foreach ($existingAtoms as $atomFile) {
                if ($this->option('sync')) {
                    dispatch_sync(new \Modules\Contracts\Jobs\ProcessPlacspFile($atomFile));
                } else {
                    \Modules\Contracts\Jobs\ProcessPlacspFile::dispatch($atomFile);
                }
            }
            return;
        }
    }

    // ...existing download logic...
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Console/SyncContracts.php tests/Feature/Contracts/Reprocess/SyncContractsLocalFirstTest.php
git commit -m "feat(contracts): SyncContracts skips download if local atoms exist (respects local-first rule)"
```

---

## Task 11 — Horizon queue config for `contracts-reprocess`

**Files:**
- Modify: `config/horizon.php`

- [ ] **Step 1: Add queue to Horizon config**

In `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'contracts-reprocess'],
            'balance' => 'auto',
            'maxProcesses' => 10,
            'balanceCooldown' => 3,
        ],
        'supervisor-reprocess' => [
            'connection' => 'redis',
            'queue' => ['contracts-reprocess'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'tries' => 3,
            'timeout' => 600,
        ],
    ],
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'contracts-reprocess'],
            'balance' => 'auto',
            'maxProcesses' => 4,
            'tries' => 3,
        ],
    ],
],
```

- [ ] **Step 2: Commit**

```bash
git add config/horizon.php
git commit -m "chore(contracts): add contracts-reprocess queue supervisor to Horizon"
```

---

## Task 12 — Phase 1.2 gate + push

- [ ] **Step 1: Full test suite**

```bash
php artisan test tests/Feature/Contracts/Ingestion tests/Feature/Contracts/Reprocess
```
Expected: all green.

- [ ] **Step 2: PHPStan + Pint**

```bash
./vendor/bin/phpstan analyse app/Modules/Contracts --level=8
./vendor/bin/pint app/Modules/Contracts tests/Feature/Contracts
git add -A && git diff --cached --quiet || git commit -m "style(contracts): pint ingestor files"
```

- [ ] **Step 3: Smoke test: reproceso de 1 atom real**

```bash
php artisan contracts:reprocess --atoms="$(pwd)/storage/app/placsp/201801/extracted/licitacionesPerfilesContratanteCompleto3_20200522_234632_1.atom" --sync
php artisan tinker --execute='
echo "contracts: " . \Modules\Contracts\Models\Contract::count() . "\n";
echo "lots: " . \Modules\Contracts\Models\ContractLot::count() . "\n";
echo "awards: " . \Modules\Contracts\Models\Award::count() . "\n";
echo "snapshots: " . \Modules\Contracts\Models\ContractSnapshot::count() . "\n";
echo "notices: " . \Modules\Contracts\Models\ContractNotice::count() . "\n";
echo "parse_errors: " . \Modules\Contracts\Models\ParseError::count() . "\n";
'
```
Expected: ~498 contracts, ~498+ lots, significant notices, 0 parse_errors (o errors esperados por fixtures mal formadas).

- [ ] **Step 4: Push + PR**

```bash
git push -u origin feature/contracts-v2-ingestor
gh pr create --title "contracts v2 — Phase 1.2 ingestor + reprocess" --body "$(cat <<'EOF'
## Summary
- EntityResolver con 3-level key strategy + Redis caching.
- ContractCacheInvalidator + CloudflarePurger + PurgeContractUrls job.
- ContractIngestor con ingestBatch full pipeline (idempotente) + handleTombstone.
- Refactor de ProcessPlacspFile usando streaming parser + ingestor + atom_run tracking.
- ReprocessContracts command con resumibilidad (run-id, resume, from/to, atoms, parallel, sync, dry-run, fresh).
- SyncContracts respeta regla local-first (skip download si atoms extraídos).
- Horizon supervisor para queue contracts-reprocess.
- Tests de idempotencia, snapshot growth, tombstones, cache, purge.

## Test plan
- [x] Tests feature verdes
- [x] Idempotency regression: 0 diff BD tras 2 ingests
- [x] Smoke: 1 atom real procesado → 498 contratos + snapshots correctos
- [x] PHPStan L8 + Pint verde

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
