# Phase 1.4 — Landing pública + Docs OpenAPI

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`.

**Goal:** Landing Blade en `/` + docs interactivas `/docs` (Scalar + Scramble) + health check `/health` + stats refresh scheduled.

**Tech Stack:** Blade, Tailwind, `dedoc/scramble`, `scalar/laravel`.

**Branch:** `feature/contracts-v2-landing`. **Worktree:** `wt-1.4-landing`. Base: `main` con 1.0 mergeada (paralelo a 1.1/1.2/1.3).

**Gate:**
- `/`, `/docs`, `/openapi.json`, `/health` → 200.
- Lighthouse ≥ 95 perf + a11y en `/`.
- Feature tests verdes.

---

## Task 1 — Install Scramble + Scalar

- [ ] **Step 1: Install**

```bash
composer require dedoc/scramble
composer require scalar/laravel
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider"
```

- [ ] **Step 2: Configure Scramble**

In `config/scramble.php`:

```php
return [
    'api_path' => 'api/v1',
    'api_domain' => null,
    'info' => [
        'version' => '1.0.0',
        'title' => 'Escáner Público API',
        'description' => 'API pública de contratos del sector público español, derivada de PLACSP.',
    ],
    'ui' => ['title' => 'Escáner Público — API'],
    'servers' => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Default'],
    ],
];
```

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/scramble.php
git commit -m "chore(contracts): install Scramble + Scalar for API docs"
```

---

## Task 2 — `LandingStatsService` + `landing:refresh-stats` command

**Files:**
- Create: `app/Modules/Contracts/Services/Stats/LandingStatsService.php`
- Create: `app/Modules/Contracts/Console/RefreshLandingStats.php`
- Test: `tests/Feature/Contracts/Landing/LandingStatsTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Services\Stats\LandingStatsService;
use Tests\TestCase;

class LandingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_returns_counters_and_top_organizations(): void
    {
        Contract::factory()->count(5)->create(['importe_con_iva' => 1000]);
        $s = app(LandingStatsService::class)->compute();
        $this->assertEquals(5, $s['total_contracts']);
        $this->assertArrayHasKey('total_amount', $s);
        $this->assertArrayHasKey('top_organizations', $s);
        $this->assertArrayHasKey('last_snapshot_at', $s);
    }

    public function test_refresh_command_caches_stats(): void
    {
        Contract::factory()->count(3)->create();
        $this->artisan('landing:refresh-stats')->assertSuccessful();
        $this->assertNotNull(Cache::get('landing:stats'));
    }
}
```

- [ ] **Step 2: Implement**

```php
<?php
// app/Modules/Contracts/Services/Stats/LandingStatsService.php
namespace Modules\Contracts\Services\Stats;

use Illuminate\Support\Facades\Cache;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\Organization;

class LandingStatsService
{
    private const CACHE_KEY = 'landing:stats';
    private const CACHE_TTL = 900;  // 15 min

    public function compute(): array
    {
        return [
            'total_contracts' => Contract::count(),
            'total_organizations' => Organization::count(),
            'total_companies' => Company::count(),
            'total_amount' => (float) Contract::sum('importe_con_iva'),
            'last_snapshot_at' => Contract::max('snapshot_updated_at'),
            'top_organizations' => Contract::selectRaw('organization_id, COUNT(*) as cnt, SUM(importe_con_iva) as total')
                ->with('organization:id,name')
                ->groupBy('organization_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->organization_id,
                    'name' => $r->organization?->name,
                    'contracts' => $r->cnt,
                    'total' => (float) $r->total,
                ])
                ->toArray(),
            'recent_awarded' => Contract::where('status_code', 'ADJ')
                ->orderByDesc('snapshot_updated_at')
                ->limit(10)
                ->get(['id','external_id','expediente','objeto','importe_con_iva','snapshot_updated_at'])
                ->toArray(),
        ];
    }

    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY) ?? $this->refresh();
    }

    public function refresh(): array
    {
        $data = $this->compute();
        Cache::put(self::CACHE_KEY, $data, self::CACHE_TTL);
        return $data;
    }
}
```

```php
<?php
// app/Modules/Contracts/Console/RefreshLandingStats.php
namespace Modules\Contracts\Console;

use Illuminate\Console\Command;
use Modules\Contracts\Services\Stats\LandingStatsService;

class RefreshLandingStats extends Command
{
    protected $signature = 'landing:refresh-stats';
    protected $description = 'Refresca los contadores de la landing (Redis cache)';

    public function handle(LandingStatsService $svc): int
    {
        $svc->refresh();
        $this->info('Landing stats refrescados.');
        return self::SUCCESS;
    }
}
```

Register in `ContractsServiceProvider::boot()` commands array + schedule in `bootstrap/app.php` or `routes/console.php`:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
Schedule::command('landing:refresh-stats')->everyFiveMinutes();
```

- [ ] **Step 3: Run test, expect PASS**

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Contracts/Services/Stats/LandingStatsService.php app/Modules/Contracts/Console/RefreshLandingStats.php app/Modules/Contracts/ContractsServiceProvider.php routes/console.php tests/Feature/Contracts/Landing/LandingStatsTest.php
git commit -m "feat(contracts): LandingStatsService + refresh scheduler"
```

---

## Task 3 — `LandingController` + Blade view

**Files:**
- Create: `app/Modules/Contracts/Http/Controllers/LandingController.php`
- Create: `resources/views/landing/index.blade.php`
- Test: `tests/Feature/Contracts/Landing/LandingPageTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_with_stats(): void
    {
        Contract::factory()->count(5)->create();
        $r = $this->get('/');
        $r->assertSuccessful();
        $r->assertSee('Escáner Público');
        $r->assertSee('API abierta');
        $r->assertHeader('Cache-Control');
    }

    public function test_home_shows_curl_snippets(): void
    {
        $r = $this->get('/');
        $r->assertSee('curl', false);
        $r->assertSee('/api/v1/contracts', false);
    }
}
```

- [ ] **Step 2: Implement controller + view**

```php
<?php
// app/Modules/Contracts/Http/Controllers/LandingController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Contracts\Services\Stats\LandingStatsService;

class LandingController extends Controller
{
    public function show(LandingStatsService $stats)
    {
        return response()
            ->view('landing.index', ['stats' => $stats->cached()])
            ->header('Cache-Control', 'public, s-maxage=300, stale-while-revalidate=3600');
    }
}
```

```blade
{{-- resources/views/landing/index.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escáner Público — API abierta de contratos del sector público</title>
    <meta name="description" content="API pública y abierta de contratos del sector público español derivada de PLACSP. {{ number_format($stats['total_contracts'] ?? 0, 0, ',', '.') }} contratos indexados.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', system-ui, sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-900">
    <main class="max-w-5xl mx-auto px-6 py-16">
        <header class="mb-16">
            <div class="text-xs tracking-widest text-indigo-600 font-semibold mb-2">ESCÁNER PÚBLICO</div>
            <h1 class="text-5xl font-bold mb-4">API abierta de contratos del sector público</h1>
            <p class="text-xl text-slate-600 max-w-2xl">
                Datos oficiales de la <a href="https://contrataciondelestado.es" class="underline">Plataforma de Contratación del Sector Público</a>, reestructurados en una API REST consultable.
            </p>
        </header>

        <section class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-16">
            <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Contratos</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($stats['total_contracts'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Órganos</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($stats['total_organizations'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Empresas</div>
                <div class="text-3xl font-bold mt-2">{{ number_format($stats['total_companies'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                <div class="text-xs text-slate-500 uppercase tracking-wider">Importe total</div>
                <div class="text-3xl font-bold mt-2">{{ number_format(($stats['total_amount'] ?? 0) / 1_000_000_000, 2, ',', '.') }}B€</div>
            </div>
        </section>

        <section class="mb-16">
            <h2 class="text-2xl font-bold mb-6">Empieza en 30 segundos</h2>
            <div class="space-y-4">
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Listar últimas adjudicaciones</div>
                    <pre class="text-emerald-400 text-sm"><code>curl "{{ config('app.url') }}/api/v1/contracts?filter[status_code]=ADJ&sort=-snapshot_updated_at"</code></pre>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Ficha de contrato con timeline y adjudicación</div>
                    <pre class="text-emerald-400 text-sm"><code>curl "{{ config('app.url') }}/api/v1/contracts/19066873?include=lots.awards.company,notices,modifications"</code></pre>
                </div>
                <div class="bg-slate-900 rounded-xl p-4 overflow-x-auto">
                    <div class="text-xs text-slate-400 mb-2">Stats de un órgano de contratación</div>
                    <pre class="text-emerald-400 text-sm"><code>curl "{{ config('app.url') }}/api/v1/organizations/1/stats"</code></pre>
                </div>
            </div>
        </section>

        @if(!empty($stats['top_organizations']))
        <section class="mb-16">
            <h2 class="text-2xl font-bold mb-6">Top 10 órganos por importe adjudicado</h2>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100 text-slate-600 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Órgano</th>
                            <th class="px-4 py-3 text-right">Contratos</th>
                            <th class="px-4 py-3 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['top_organizations'] as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ $row['name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($row['contracts'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ number_format($row['total'] / 1_000_000, 2, ',', '.') }}M€</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        <section class="mb-16">
            <h2 class="text-2xl font-bold mb-6">Recursos</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="/docs" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 transition">
                    <div class="font-semibold mb-1">📖 Docs interactivas</div>
                    <div class="text-sm text-slate-600">Todos los endpoints, filtros, includes. Prueba en el navegador.</div>
                </a>
                <a href="/openapi.json" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 transition">
                    <div class="font-semibold mb-1">📄 OpenAPI spec</div>
                    <div class="text-sm text-slate-600">Genera clientes en cualquier lenguaje con openapi-generator.</div>
                </a>
                <a href="https://github.com/christianpasinrey/escanerpublico-backend" class="bg-white rounded-xl p-6 border border-slate-200 hover:border-indigo-500 transition">
                    <div class="font-semibold mb-1">💻 GitHub</div>
                    <div class="text-sm text-slate-600">Código abierto. Contribuciones bienvenidas.</div>
                </a>
            </div>
        </section>

        <section class="mb-16 bg-slate-100 rounded-xl p-6 text-sm text-slate-700">
            <div class="font-semibold mb-2">Rate limits</div>
            <div>60 req/min por IP sin auth · 600 req/min con <code class="bg-white px-1 rounded">X-Api-Key</code> (disponible próximamente — hoy la API es totalmente abierta).</div>
        </section>

        <footer class="text-xs text-slate-500 border-t border-slate-200 pt-6">
            Datos oficiales de PLACSP (Plataforma de Contratación del Sector Público). Escáner Público no es una entidad pública — solo una capa de presentación que reestructura datos abiertos. Última sincronización: {{ $stats['last_snapshot_at'] ?? 'N/A' }}.
        </footer>
    </main>
</body>
</html>
```

- [ ] **Step 3: Register web route**

```php
<?php
// app/Modules/Contracts/Routes/web.php
use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\LandingController;
use Modules\Contracts\Http\Controllers\HealthController;

Route::get('/', [LandingController::class, 'show']);
Route::get('/health', [HealthController::class, 'show']);
```

Load from `ContractsServiceProvider::boot()`:

```php
$this->loadRoutesFrom(__DIR__.'/Routes/web.php');
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/LandingController.php resources/views/landing/index.blade.php app/Modules/Contracts/Routes/web.php app/Modules/Contracts/ContractsServiceProvider.php tests/Feature/Contracts/Landing/LandingPageTest.php
git commit -m "feat(contracts): landing page at / with live stats + curl snippets"
```

---

## Task 4 — `HealthController`

**Files:**
- Create: `app/Modules/Contracts/Http/Controllers/HealthController.php`
- Test: `tests/Feature/Contracts/Landing/HealthCheckTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_status_and_counters(): void
    {
        Contract::factory()->count(2)->create();
        $r = $this->getJson('/health');
        $r->assertSuccessful();
        $r->assertJsonStructure(['status','snapshot_updated_at','contracts']);
        $r->assertJsonPath('status', 'ok');
        $r->assertJsonPath('contracts', 2);
    }
}
```

- [ ] **Step 2: Implement**

```php
<?php
// app/Modules/Contracts/Http/Controllers/HealthController.php
namespace Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Contracts\Models\Contract;

class HealthController extends Controller
{
    public function show()
    {
        return response()->json([
            'status' => 'ok',
            'snapshot_updated_at' => Contract::max('snapshot_updated_at'),
            'contracts' => Contract::count(),
        ])->header('Cache-Control', 'no-store');
    }
}
```

- [ ] **Step 3: Run test, expect PASS**

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Contracts/Http/Controllers/HealthController.php tests/Feature/Contracts/Landing/HealthCheckTest.php
git commit -m "feat(contracts): add /health endpoint"
```

---

## Task 5 — Mount Scalar UI at `/docs` + `/openapi.json`

**Files:**
- Modify: `app/Modules/Contracts/Routes/web.php`
- Test: `tests/Feature/Contracts/Landing/DocsTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Feature\Contracts\Landing;

use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_openapi_json_returns_valid_spec(): void
    {
        $r = $this->getJson('/openapi.json');
        $r->assertSuccessful();
        $r->assertJsonStructure(['openapi','info','paths']);
        $r->assertJsonPath('openapi', fn($v) => str_starts_with($v, '3.'));
    }

    public function test_docs_page_renders(): void
    {
        $r = $this->get('/docs');
        $r->assertSuccessful();
    }
}
```

- [ ] **Step 2: Configure Scalar**

Scalar's Laravel package auto-registers `/docs`. Configure it in `config/scalar.php` to read from `/openapi.json`:

```php
<?php
// config/scalar.php
return [
    'url' => '/openapi.json',
    'theme' => 'bluePlanet',
    'layout' => 'modern',
    'title' => 'Escáner Público — API Reference',
];
```

Scramble auto-registers `/openapi.json` — confirm in its route output:

```bash
php artisan route:list | grep -E '(openapi|docs)'
```

If Scramble uses a different default path, either change via config or add a custom route that forwards:

```php
// app/Modules/Contracts/Routes/web.php
Route::get('/openapi.json', fn() => app(\Dedoc\Scramble\Generator::class)())->name('openapi');
```

- [ ] **Step 3: Run test, expect PASS**

- [ ] **Step 4: Commit**

```bash
git add config/scalar.php app/Modules/Contracts/Routes/web.php tests/Feature/Contracts/Landing/DocsTest.php
git commit -m "feat(contracts): mount Scalar UI at /docs + Scramble OpenAPI at /openapi.json"
```

---

## Task 6 — Phase 1.4 gate + push

- [ ] **Step 1: Full test suite**

```bash
php artisan test tests/Feature/Contracts/Landing
```

- [ ] **Step 2: Manual smoke tests**

```bash
curl -I http://localhost/
curl http://localhost/health
curl -s http://localhost/openapi.json | jq '.info.title'
```

- [ ] **Step 3: Lighthouse** (corre en local con Chrome o GH Actions)

```bash
npx lighthouse http://localhost/ --only-categories=performance,accessibility --output=json --output-path=/tmp/lh.json
jq '.categories.performance.score, .categories.accessibility.score' /tmp/lh.json
```
Expected: ambos ≥ 0.95.

- [ ] **Step 4: PHPStan + Pint**

```bash
./vendor/bin/phpstan analyse app/Modules/Contracts --level=8
./vendor/bin/pint app/Modules/Contracts/Http/Controllers/LandingController.php app/Modules/Contracts/Http/Controllers/HealthController.php app/Modules/Contracts/Services/Stats/LandingStatsService.php
git add -A && git diff --cached --quiet || git commit -m "style(contracts): pint landing files"
```

- [ ] **Step 5: Push + PR**

```bash
git push -u origin feature/contracts-v2-landing
gh pr create --title "contracts v2 — Phase 1.4 landing + docs" --body "$(cat <<'EOF'
## Summary
- Landing `/` Blade con Tailwind, stats en vivo, 3 snippets curl, top órganos, rate limits.
- LandingStatsService + refresh scheduler (5 min).
- HealthController `/health`.
- Scramble auto-genera OpenAPI en `/openapi.json`.
- Scalar UI en `/docs`.

## Test plan
- [x] Feature tests verdes
- [x] Lighthouse ≥ 95 perf + a11y
- [x] PHPStan L8 verde

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
