# Phase 1.3 — API (query builder + resources + cache headers)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`.

**Goal:** API pública query-builder con endpoints de contracts, lots, organizations, companies + cache headers + feature tests + benchmarks.

**Architecture:** `spatie/laravel-query-builder` con whitelist explícita. API Resources con `whenLoaded`. Middleware `LimitNestedIncludes`. Headers `Cache-Control` por endpoint.

**Tech Stack:** Laravel 12, spatie/laravel-query-builder, Pest, pestphp/pest-plugin-benchmarks.

**Branch:** `feature/contracts-v2-api`. **Worktree:** `wt-1.3-api`. Base: `main` con 1.0 mergeada (puede paralelo a 1.1/1.2 — usa fixtures para datos de test).

**Gate:**
- Feature tests de todos los endpoints verdes.
- Tests de include/field/filter whitelist (401 / 400 si no permitido).
- Benchmarks bajo threshold (warm <300ms, cold <700ms).
- PHPStan L8 verde.

---

## Task 1 — Install `spatie/laravel-query-builder` + pest benchmarks

- [ ] **Step 1: Install**

```bash
composer require spatie/laravel-query-builder
composer require --dev pestphp/pest-plugin-benchmarks
```

- [ ] **Step 2: Publish config**

```bash
php artisan vendor:publish --provider="Spatie\QueryBuilder\QueryBuilderServiceProvider"
```

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/query-builder.php
git commit -m "chore(contracts): add spatie/laravel-query-builder + pest-benchmarks"
```

---

## Task 2 — `LimitNestedIncludes` middleware

**Files:**
- Create: `app/Modules/Contracts/Http/Middleware/LimitNestedIncludes.php`
- Test: `tests/Feature/Contracts/Api/LimitNestedIncludesTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitNestedIncludesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_include_with_more_than_3_nesting_levels(): void
    {
        $resp = $this->getJson('/api/v1/contracts?include=a.b.c.d');
        $resp->assertStatus(400);
        $resp->assertJsonPath('error', 'include_too_deep');
    }

    public function test_allows_3_levels(): void
    {
        $resp = $this->getJson('/api/v1/contracts?include=lots.awards.company');
        $resp->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement middleware**

```php
<?php
// app/Modules/Contracts/Http/Middleware/LimitNestedIncludes.php
namespace Modules\Contracts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitNestedIncludes
{
    public function handle(Request $request, Closure $next, int $maxDepth = 3): Response
    {
        $include = (string) $request->query('include', '');
        if ($include === '') return $next($request);

        foreach (explode(',', $include) as $item) {
            if (substr_count(trim($item), '.') >= $maxDepth) {
                return response()->json([
                    'error' => 'include_too_deep',
                    'message' => "Max include depth is {$maxDepth} (got: ".substr_count($item, '.').")",
                ], 400);
            }
        }
        return $next($request);
    }
}
```

Register in `ContractsServiceProvider::boot()`:

```php
$this->app['router']->aliasMiddleware('limit.includes', \Modules\Contracts\Http\Middleware\LimitNestedIncludes::class);
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Http/Middleware/LimitNestedIncludes.php app/Modules/Contracts/ContractsServiceProvider.php tests/Feature/Contracts/Api/LimitNestedIncludesTest.php
git commit -m "feat(contracts): add LimitNestedIncludes middleware (max 3 levels)"
```

---

## Task 3 — `RelevanceSort` custom sort

**Files:**
- Create: `app/Modules/Contracts/Http/Sorts/RelevanceSort.php`

- [ ] **Step 1: Implement**

```php
<?php
// app/Modules/Contracts/Http/Sorts/RelevanceSort.php
namespace Modules\Contracts\Http\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

class RelevanceSort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): Builder
    {
        $search = request()->query('filter.search');
        if (!$search) return $query->orderBy('snapshot_updated_at', $descending ? 'desc' : 'asc');
        return $query->orderByRaw(
            'MATCH(objeto, expediente) AGAINST (? IN NATURAL LANGUAGE MODE) '.($descending ? 'DESC' : 'ASC'),
            [$search],
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Contracts/Http/Sorts/RelevanceSort.php
git commit -m "feat(contracts): add RelevanceSort for FULLTEXT-backed sort"
```

---

## Task 4 — `ContractResource` + `LotResource` + `AwardResource`

**Files:**
- Create: `app/Modules/Contracts/Http/Resources/ContractResource.php`
- Create: `app/Modules/Contracts/Http/Resources/LotResource.php`
- Create: `app/Modules/Contracts/Http/Resources/AwardResource.php`
- Test: `tests/Feature/Contracts/Api/ResourceStructureTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Http\Resources\ContractResource;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ResourceStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_resource_base_fields(): void
    {
        $c = Contract::factory()->create();
        $arr = (new ContractResource($c))->toArray(request());
        foreach (['id','external_id','expediente','objeto','status_code','importe_con_iva','snapshot_updated_at'] as $k) {
            $this->assertArrayHasKey($k, $arr);
        }
        $this->assertArrayNotHasKey('lots', $arr);  // no include → not loaded
    }

    public function test_contract_resource_includes_lots_when_loaded(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create();
        $c->load('lots');
        $arr = (new ContractResource($c))->toArray(request());
        $this->assertArrayHasKey('lots', $arr);
        $this->assertCount(1, $arr['lots']);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement resources**

```php
<?php
// app/Modules/Contracts/Http/Resources/ContractResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray($request): array
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
            'timeline' => $this->whenLoaded('notices', fn() => $this->buildTimeline()),
            'snapshots_summary' => SnapshotSummaryResource::collection($this->whenLoaded('snapshots')),
        ];
    }

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
                    'title' => ucfirst($m->type),
                    'description' => $m->description,
                ];
            }
        }
        usort($events, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
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
```

```php
<?php
// app/Modules/Contracts/Http/Resources/LotResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LotResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lot_number' => $this->lot_number,
            'title' => $this->title,
            'description' => $this->description,
            'tipo_contrato_code' => $this->tipo_contrato_code,
            'cpv_codes' => $this->cpv_codes,
            'budget_with_tax' => $this->budget_with_tax,
            'budget_without_tax' => $this->budget_without_tax,
            'estimated_value' => $this->estimated_value,
            'duration' => $this->duration,
            'duration_unit' => $this->duration_unit,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'nuts_code' => $this->nuts_code,
            'lugar_ejecucion' => $this->lugar_ejecucion,

            'awards' => AwardResource::collection($this->whenLoaded('awards')),
            'criteria' => CriterionResource::collection($this->whenLoaded('criteria')),
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/AwardResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AwardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'amount_without_tax' => $this->amount_without_tax,
            'description' => $this->description,
            'award_date' => $this->award_date?->toDateString(),
            'formalization_date' => $this->formalization_date?->toDateString(),
            'contract_number' => $this->contract_number,
            'sme_awarded' => $this->sme_awarded,
            'num_offers' => $this->num_offers,
            'lower_tender_amount' => $this->lower_tender_amount,
            'higher_tender_amount' => $this->higher_tender_amount,
            'result_code' => $this->result_code,

            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Http/Resources tests/Feature/Contracts/Api/ResourceStructureTest.php
git commit -m "feat(contracts): add ContractResource + LotResource + AwardResource"
```

---

## Task 5 — Remaining Resources (Organization, Company, Notice, Document, Modification, Criterion, Snapshot)

**Files:**
- Create: `app/Modules/Contracts/Http/Resources/OrganizationResource.php`
- Create: `app/Modules/Contracts/Http/Resources/CompanyResource.php`
- Create: `app/Modules/Contracts/Http/Resources/NoticeResource.php`
- Create: `app/Modules/Contracts/Http/Resources/DocumentResource.php`
- Create: `app/Modules/Contracts/Http/Resources/ModificationResource.php`
- Create: `app/Modules/Contracts/Http/Resources/CriterionResource.php`
- Create: `app/Modules/Contracts/Http/Resources/SnapshotSummaryResource.php`
- Create: `app/Modules/Contracts/Http/Resources/SnapshotFullResource.php`

- [ ] **Step 1: Create all**

```php
<?php
// app/Modules/Contracts/Http/Resources/OrganizationResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'nif' => $this->nif,
            'type_code' => $this->type_code,
            'activity_code' => $this->activity_code,
            'buyer_profile_uri' => $this->buyer_profile_uri,
            'hierarchy' => $this->hierarchy,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'contracts' => ContractResource::collection($this->whenLoaded('contracts')),
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/CompanyResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nif' => $this->nif,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'awards' => AwardResource::collection($this->whenLoaded('awards')),
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/NoticeResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NoticeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'notice_type_code' => $this->notice_type_code,
            'publication_media' => $this->publication_media,
            'issue_date' => $this->issue_date?->toDateString(),
            'document_uri' => $this->document_uri,
            'document_filename' => $this->document_filename,
            'document_type_code' => $this->document_type_code,
            'document_type_name' => $this->document_type_name,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/DocumentResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'uri' => $this->uri,
            'hash' => $this->hash,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/ModificationResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ModificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type' => $this->type,
            'issue_date' => $this->issue_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'description' => $this->description,
            'amount_delta' => $this->amount_delta,
            'new_end_date' => $this->new_end_date?->toDateString(),
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/CriterionResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CriterionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type_code' => $this->type_code,
            'subtype_code' => $this->subtype_code,
            'description' => $this->description,
            'note' => $this->note,
            'weight_numeric' => $this->weight_numeric,
            'sort_order' => $this->sort_order,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/SnapshotSummaryResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnapshotSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'entry_updated_at' => $this->entry_updated_at->toIso8601String(),
            'status_code' => $this->status_code,
            'content_hash' => $this->content_hash,
            'source_atom' => $this->source_atom,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/SnapshotFullResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnapshotFullResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'entry_updated_at' => $this->entry_updated_at->toIso8601String(),
            'status_code' => $this->status_code,
            'content_hash' => $this->content_hash,
            'source_atom' => $this->source_atom,
            'payload' => $this->payload,
            'ingested_at' => $this->ingested_at->toIso8601String(),
        ];
    }
}
```

Also create `AddressResource` and `ContactResource` (polymorphic):

```php
<?php
// app/Modules/Contracts/Http/Resources/AddressResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'line' => $this->line,
            'postal_code' => $this->postal_code,
            'city_name' => $this->city_name,
            'country_code' => $this->country_code,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Resources/ContactResource.php
namespace Modules\Contracts\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Contracts/Http/Resources
git commit -m "feat(contracts): add all remaining API Resources"
```

---

## Task 6 — `ContractController` with query builder

**Files:**
- Modify: `app/Modules/Contracts/Http/Controllers/ContractController.php`
- Create: `app/Modules/Contracts/Http/Filters/SearchFilter.php`
- Create: `app/Modules/Contracts/Http/Filters/AmountBetweenFilter.php`
- Test: `tests/Feature/Contracts/Api/ContractIndexTest.php`
- Test: `tests/Feature/Contracts/Api/ContractShowTest.php`

- [ ] **Step 1: Write tests**

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class ContractIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginated(): void
    {
        Contract::factory()->count(30)->create(['status_code' => 'ADJ']);
        $r = $this->getJson('/api/v1/contracts?per_page=10');
        $r->assertSuccessful();
        $r->assertJsonStructure(['data','meta','links']);
        $r->assertJsonCount(10, 'data');
    }

    public function test_filter_by_status(): void
    {
        Contract::factory()->count(3)->create(['status_code' => 'ADJ']);
        Contract::factory()->count(5)->create(['status_code' => 'RES']);
        $r = $this->getJson('/api/v1/contracts?filter[status_code]=ADJ');
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data');
    }

    public function test_include_organization(): void
    {
        $c = Contract::factory()->create();
        $r = $this->getJson('/api/v1/contracts?include=organization&per_page=1');
        $r->assertSuccessful();
        $r->assertJsonPath('data.0.organization.id', $c->organization_id);
    }

    public function test_disallowed_include_returns_400(): void
    {
        $r = $this->getJson('/api/v1/contracts?include=evil');
        $r->assertStatus(400);
    }

    public function test_cache_headers_present(): void
    {
        $r = $this->getJson('/api/v1/contracts');
        $r->assertHeader('Cache-Control');
        $this->assertStringContainsString('s-maxage', $r->headers->get('Cache-Control'));
    }
}
```

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ContractShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_by_external_id(): void
    {
        $c = Contract::factory()->create(['external_id' => 'https://x/12345']);
        $r = $this->getJson('/api/v1/contracts/'.urlencode($c->external_id));
        $r->assertSuccessful();
        $r->assertJsonPath('data.external_id', $c->external_id);
    }

    public function test_show_includes_lots_and_awards(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create(['lot_number' => 1]);
        $r = $this->getJson('/api/v1/contracts/'.urlencode($c->external_id).'?include=lots');
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.lots');
    }
}
```

- [ ] **Step 2: Run tests, expect FAIL**

- [ ] **Step 3: Implement Filter classes**

```php
<?php
// app/Modules/Contracts/Http/Filters/SearchFilter.php
namespace Modules\Contracts\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class SearchFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        if ($value === null || $value === '') return $query;
        return $query->whereRaw(
            'MATCH(objeto, expediente) AGAINST (? IN NATURAL LANGUAGE MODE)',
            [$value],
        );
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Filters/AmountBetweenFilter.php
namespace Modules\Contracts\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class AmountBetweenFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): Builder
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $min = is_numeric($parts[0] ?? null) ? (float) $parts[0] : null;
        $max = is_numeric($parts[1] ?? null) ? (float) $parts[1] : null;
        if ($min !== null) $query->where('importe_con_iva', '>=', $min);
        if ($max !== null) $query->where('importe_con_iva', '<=', $max);
        return $query;
    }
}
```

- [ ] **Step 4: Implement controller**

```php
<?php
// app/Modules/Contracts/Http/Controllers/ContractController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Filters\AmountBetweenFilter;
use Modules\Contracts\Http\Filters\SearchFilter;
use Modules\Contracts\Http\Resources\ContractResource;
use Modules\Contracts\Http\Sorts\RelevanceSort;
use Modules\Contracts\Models\Contract;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $q = QueryBuilder::for(Contract::class)
            ->allowedFilters([
                AllowedFilter::exact('status_code'),
                AllowedFilter::exact('tipo_contrato_code'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('nuts_code'),
                AllowedFilter::exact('funding_program_code'),
                AllowedFilter::exact('over_threshold_indicator'),
                AllowedFilter::custom('search', new SearchFilter()),
                AllowedFilter::custom('amount_between', new AmountBetweenFilter()),
            ])
            ->allowedIncludes([
                'organization','organization.addresses','organization.contacts',
                'lots','lots.awards','lots.awards.company','lots.awards.company.addresses',
                'lots.criteria',
                'notices','modifications','documents','snapshots',
            ])
            ->allowedFields([
                'id','external_id','expediente','objeto','status_code','tipo_contrato_code',
                'importe_con_iva','importe_sin_iva','valor_estimado','fecha_inicio','fecha_fin',
                'snapshot_updated_at','annulled_at','organization_id',
            ])
            ->allowedSorts([
                'snapshot_updated_at','importe_con_iva','fecha_inicio',
                AllowedSort::custom('relevance', new RelevanceSort()),
            ])
            ->defaultSort('-snapshot_updated_at');

        $paginated = $q->paginate($perPage)->appends($request->query());

        return ContractResource::collection($paginated)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=60, stale-while-revalidate=300');
    }

    public function show(string $externalId)
    {
        // Routing by external_id (URL-encoded or numeric PLACSP id suffix)
        $decoded = urldecode($externalId);
        $query = Contract::query();
        if (str_starts_with($decoded, 'http')) {
            $query->where('external_id', $decoded);
        } else {
            $query->where('external_id', 'LIKE', '%/'.$decoded);
        }

        $contract = QueryBuilder::for($query)
            ->allowedIncludes([
                'organization','organization.addresses','organization.contacts',
                'lots','lots.awards','lots.awards.company','lots.awards.company.addresses',
                'lots.criteria',
                'notices','modifications','documents','snapshots',
            ])
            ->firstOrFail();

        return ContractResource::make($contract)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=86400');
    }
}
```

- [ ] **Step 5: Update routes**

```php
<?php
// app/Modules/Contracts/Routes/api.php
use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\OrganizationController;
use Modules\Contracts\Http\Controllers\CompanyController;
use Modules\Contracts\Http\Controllers\LotController;

Route::prefix('api/v1')->middleware(['limit.includes'])->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{external_id}', [ContractController::class, 'show'])->where('external_id', '.*');

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::get('/organizations/{organization}/stats', [OrganizationController::class, 'stats']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
    Route::get('/companies/{company}/stats', [CompanyController::class, 'stats']);

    Route::get('/lots', [LotController::class, 'index']);
});
```

- [ ] **Step 6: Run tests, expect PASS**

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/ContractController.php app/Modules/Contracts/Http/Filters app/Modules/Contracts/Routes/api.php tests/Feature/Contracts/Api/ContractIndexTest.php tests/Feature/Contracts/Api/ContractShowTest.php
git commit -m "feat(contracts): ContractController with spatie query builder + cache headers"
```

---

## Task 7 — `OrganizationController` with query builder + stats

**Files:**
- Modify: `app/Modules/Contracts/Http/Controllers/OrganizationController.php`
- Create: `app/Modules/Contracts/Services/Stats/OrganizationStatsService.php`
- Test: `tests/Feature/Contracts/Api/OrganizationEndpointsTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;
use Tests\TestCase;

class OrganizationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index(): void
    {
        Organization::factory()->count(5)->create();
        $r = $this->getJson('/api/v1/organizations');
        $r->assertSuccessful();
        $r->assertJsonCount(5, 'data');
    }

    public function test_show_with_contracts_include(): void
    {
        $o = Organization::factory()->create();
        Contract::factory()->for($o, 'organization')->count(3)->create();
        $r = $this->getJson("/api/v1/organizations/{$o->id}?include=contracts");
        $r->assertSuccessful();
        $r->assertJsonCount(3, 'data.contracts');
    }

    public function test_stats(): void
    {
        $o = Organization::factory()->create();
        Contract::factory()->for($o, 'organization')->count(10)->create(['importe_con_iva' => 1000, 'status_code' => 'ADJ']);
        $r = $this->getJson("/api/v1/organizations/{$o->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonStructure(['total_contracts','total_amount','by_status','by_year']);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Implement stats service**

```php
<?php
// app/Modules/Contracts/Services/Stats/OrganizationStatsService.php
namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\DB;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

class OrganizationStatsService
{
    public function compute(Organization $org): array
    {
        $base = Contract::where('organization_id', $org->id);

        $totalContracts = (clone $base)->count();
        $totalAmount = (float) (clone $base)->sum('importe_con_iva');

        $byStatus = (clone $base)->selectRaw('status_code, COUNT(*) as cnt')
            ->groupBy('status_code')->pluck('cnt','status_code')->toArray();

        $byType = (clone $base)->selectRaw('tipo_contrato_code, COUNT(*) as cnt')
            ->groupBy('tipo_contrato_code')->pluck('cnt','tipo_contrato_code')->toArray();

        $byYear = (clone $base)->selectRaw('YEAR(fecha_inicio) as y, SUM(importe_con_iva) as total')
            ->whereNotNull('fecha_inicio')
            ->groupBy('y')->orderBy('y')->pluck('total','y')->toArray();

        $uniqueCompanies = DB::table('awards')
            ->join('contract_lots', 'awards.contract_lot_id', '=', 'contract_lots.id')
            ->where('contract_lots.contract_id', '!=', null)
            ->whereIn('contract_lots.contract_id', (clone $base)->pluck('id'))
            ->distinct('awards.company_id')
            ->count('awards.company_id');

        return [
            'total_contracts' => $totalContracts,
            'total_amount' => $totalAmount,
            'avg_amount' => $totalContracts > 0 ? round($totalAmount / $totalContracts, 2) : 0,
            'unique_companies' => $uniqueCompanies,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'by_year' => $byYear,
        ];
    }
}
```

- [ ] **Step 4: Implement controller**

```php
<?php
// app/Modules/Contracts/Http/Controllers/OrganizationController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\OrganizationResource;
use Modules\Contracts\Models\Organization;
use Modules\Contracts\Services\Stats\OrganizationStatsService;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginated = QueryBuilder::for(Organization::class)
            ->allowedFilters(['identifier','nif','type_code','activity_code'])
            ->allowedIncludes(['addresses','contacts','contracts'])
            ->allowedSorts(['name','created_at'])
            ->defaultSort('name')
            ->paginate($perPage)->appends($request->query());

        return OrganizationResource::collection($paginated)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=60, stale-while-revalidate=300');
    }

    public function show(int $id)
    {
        $org = QueryBuilder::for(Organization::where('id', $id))
            ->allowedIncludes(['addresses','contacts','contracts'])
            ->firstOrFail();

        return OrganizationResource::make($org)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=86400');
    }

    public function stats(Organization $organization, OrganizationStatsService $stats)
    {
        return response()
            ->json($stats->compute($organization))
            ->header('Cache-Control', 'public, s-maxage=900, stale-while-revalidate=3600');
    }
}
```

- [ ] **Step 5: Run tests, expect PASS**

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/OrganizationController.php app/Modules/Contracts/Services/Stats/OrganizationStatsService.php tests/Feature/Contracts/Api/OrganizationEndpointsTest.php
git commit -m "feat(contracts): OrganizationController with query builder + stats"
```

---

## Task 8 — `CompanyController` with query builder + stats + `LotController`

**Files:**
- Create: `app/Modules/Contracts/Http/Controllers/CompanyController.php`
- Create: `app/Modules/Contracts/Http/Controllers/LotController.php`
- Create: `app/Modules/Contracts/Services/Stats/CompanyStatsService.php`
- Test: `tests/Feature/Contracts/Api/CompanyEndpointsTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class CompanyEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index(): void
    {
        Company::factory()->count(3)->create();
        $r = $this->getJson('/api/v1/companies');
        $r->assertSuccessful();
    }

    public function test_show_with_awards(): void
    {
        $c = Company::factory()->create();
        $lot = ContractLot::factory()->create();
        Award::factory()->for($c)->for($lot, 'contractLot')->create();
        $r = $this->getJson("/api/v1/companies/{$c->id}?include=awards");
        $r->assertSuccessful();
        $r->assertJsonCount(1, 'data.awards');
    }

    public function test_stats(): void
    {
        $c = Company::factory()->create();
        $r = $this->getJson("/api/v1/companies/{$c->id}/stats");
        $r->assertSuccessful();
        $r->assertJsonStructure(['total_awards','total_amount','by_year']);
    }
}
```

- [ ] **Step 2: Implement stats service + controllers**

```php
<?php
// app/Modules/Contracts/Services/Stats/CompanyStatsService.php
namespace Modules\Contracts\Services\Stats;

use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;

class CompanyStatsService
{
    public function compute(Company $c): array
    {
        $base = Award::where('company_id', $c->id);

        $totalAwards = (clone $base)->count();
        $totalAmount = (float) (clone $base)->sum('amount');

        $byYear = (clone $base)->selectRaw('YEAR(award_date) as y, SUM(amount) as total')
            ->whereNotNull('award_date')
            ->groupBy('y')->orderBy('y')->pluck('total','y')->toArray();

        $topOrgs = \Illuminate\Support\Facades\DB::table('awards')
            ->join('contract_lots','awards.contract_lot_id','=','contract_lots.id')
            ->join('contracts','contract_lots.contract_id','=','contracts.id')
            ->where('awards.company_id', $c->id)
            ->selectRaw('contracts.organization_id, COUNT(*) as cnt, SUM(awards.amount) as total')
            ->groupBy('contracts.organization_id')
            ->orderByDesc('total')->limit(10)->get()->toArray();

        return [
            'total_awards' => $totalAwards,
            'total_amount' => $totalAmount,
            'by_year' => $byYear,
            'top_organizations' => $topOrgs,
        ];
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Controllers/CompanyController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\CompanyResource;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Services\Stats\CompanyStatsService;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $paginated = QueryBuilder::for(Company::class)
            ->allowedFilters(['nif','name'])
            ->allowedIncludes(['addresses','awards'])
            ->allowedSorts(['name','created_at'])
            ->defaultSort('name')
            ->paginate($perPage)->appends($request->query());

        return CompanyResource::collection($paginated)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=60, stale-while-revalidate=300');
    }

    public function show(int $id)
    {
        $company = QueryBuilder::for(Company::where('id', $id))
            ->allowedIncludes(['addresses','awards','awards.contractLot','awards.contractLot.contract','awards.contractLot.contract.organization'])
            ->firstOrFail();

        return CompanyResource::make($company)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=3600, stale-while-revalidate=86400');
    }

    public function stats(Company $company, CompanyStatsService $stats)
    {
        return response()
            ->json($stats->compute($company))
            ->header('Cache-Control', 'public, s-maxage=900, stale-while-revalidate=3600');
    }
}
```

```php
<?php
// app/Modules/Contracts/Http/Controllers/LotController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Contracts\Http\Resources\LotResource;
use Modules\Contracts\Models\ContractLot;
use Spatie\QueryBuilder\QueryBuilder;

class LotController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $paginated = QueryBuilder::for(ContractLot::class)
            ->allowedFilters(['contract_id','tipo_contrato_code','nuts_code'])
            ->allowedIncludes(['contract','awards','awards.company','criteria'])
            ->allowedSorts(['lot_number','created_at'])
            ->paginate($perPage)->appends($request->query());

        return LotResource::collection($paginated)
            ->response()
            ->header('Cache-Control', 'public, s-maxage=60, stale-while-revalidate=300');
    }
}
```

- [ ] **Step 3: Run tests, expect PASS**

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/CompanyController.php app/Modules/Contracts/Http/Controllers/LotController.php app/Modules/Contracts/Services/Stats/CompanyStatsService.php tests/Feature/Contracts/Api/CompanyEndpointsTest.php
git commit -m "feat(contracts): CompanyController + LotController + CompanyStatsService"
```

---

## Task 9 — API benchmarks

**Files:**
- Create: `tests/Benchmarks/ApiEndpointBenchmark.php`

- [ ] **Step 1: Write benchmarks**

```php
<?php
namespace Tests\Benchmarks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class ApiEndpointBenchmark extends TestCase
{
    use RefreshDatabase;

    public function test_contract_index_under_300ms_warm_cache(): void
    {
        Contract::factory()->count(100)->create();

        // warm cache
        $this->getJson('/api/v1/contracts?per_page=25');

        $start = hrtime(true);
        $this->getJson('/api/v1/contracts?per_page=25');
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan(300, $durationMs, "Warm cache took {$durationMs}ms");
    }

    public function test_contract_show_under_700ms_cold_cache(): void
    {
        $c = Contract::factory()->create();
        \Illuminate\Support\Facades\Cache::flush();

        $start = hrtime(true);
        $this->getJson("/api/v1/contracts/".urlencode($c->external_id)."?include=lots,notices,documents");
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan(700, $durationMs, "Cold cache took {$durationMs}ms");
    }
}
```

- [ ] **Step 2: Run**

```bash
php artisan test tests/Benchmarks/ApiEndpointBenchmark.php
```

- [ ] **Step 3: Commit**

```bash
git add tests/Benchmarks/ApiEndpointBenchmark.php
git commit -m "test(contracts): add API endpoint benchmarks (warm <300ms, cold <700ms)"
```

---

## Task 10 — Phase 1.3 gate + push

- [ ] **Step 1: Full test suite**

```bash
php artisan test tests/Feature/Contracts/Api tests/Benchmarks
```

- [ ] **Step 2: PHPStan + Pint**

```bash
./vendor/bin/phpstan analyse app/Modules/Contracts --level=8
./vendor/bin/pint app/Modules/Contracts tests/Feature/Contracts/Api tests/Benchmarks
git add -A && git diff --cached --quiet || git commit -m "style(contracts): pint api files"
```

- [ ] **Step 3: Smoke**

```bash
curl -s http://localhost/api/v1/contracts?per_page=5 | jq '.data | length'
curl -s "http://localhost/api/v1/contracts?include=organization&per_page=1" | jq '.data[0].organization.name'
```

- [ ] **Step 4: Push + PR**

```bash
git push -u origin feature/contracts-v2-api
gh pr create --title "contracts v2 — Phase 1.3 API + query builder" --body "$(cat <<'EOF'
## Summary
- ContractController, OrganizationController, CompanyController, LotController con QueryBuilder.
- 13 API Resources con whenLoaded.
- LimitNestedIncludes middleware (max 3 niveles).
- SearchFilter + AmountBetweenFilter + RelevanceSort.
- Stats services (Organization, Company).
- Cache-Control headers por endpoint.
- Benchmarks Pest (<300ms warm, <700ms cold).

## Test plan
- [x] Feature tests verdes (index, show, filters, includes, whitelist)
- [x] Benchmarks bajo threshold
- [x] PHPStan L8 verde

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
