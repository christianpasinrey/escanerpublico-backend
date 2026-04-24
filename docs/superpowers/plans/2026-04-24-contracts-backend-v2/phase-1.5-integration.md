# Phase 1.5 — Integration: reproceso total + Infection + CI gates

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development`.

**Goal:** Validar el stack completo end-to-end con el reproceso de 88 meses locales, activar Infection, establecer CI gates bloqueantes.

**Tech Stack:** Infection (mutation testing), GitHub Actions, PHPUnit coverage, Pest benchmarks, Horizon.

**Branch:** `feature/contracts-v2-integration`. **Worktree:** `wt-1.5-integration`. Base: `main` con 1.0+1.1+1.2+1.3+1.4 mergeadas.

**Gate final:**
- Reproceso de 88 meses completado con 4 workers en menos de 10 minutos.
- Coverage ≥ 85% en `app/Modules/Contracts/`.
- Infection MSI ≥ 70%.
- `parse_errors` solo contiene errores esperados (fixtures controladas).
- Benchmarks bajo threshold.
- PR final mergeable sin warnings.

---

## Task 1 — Install Infection + config

- [ ] **Step 1: Install**

```bash
composer require --dev infection/infection
```

- [ ] **Step 2: Create config**

```json
// infection.json5
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": [
            "app/Modules/Contracts/Services/Parser",
            "app/Modules/Contracts/Services/ContractIngestor.php",
            "app/Modules/Contracts/Services/EntityResolver.php"
        ]
    },
    "timeout": 30,
    "logs": {
        "text": "infection.log",
        "html": "infection.html",
        "summary": "infection-summary.log",
        "json": "infection.json"
    },
    "testFramework": "phpunit",
    "testFrameworkOptions": "--testsuite=Unit,Feature --filter=Contracts",
    "minMsi": 70,
    "minCoveredMsi": 70
}
```

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock infection.json5
git commit -m "chore(contracts): install Infection with MSI>=70 gate on Contracts module"
```

---

## Task 2 — Coverage config in `phpunit.xml`

**Files:**
- Modify: `phpunit.xml`

- [ ] **Step 1: Add coverage thresholds**

```xml
<coverage>
    <report>
        <html outputDirectory="build/coverage-html" />
        <clover outputFile="build/logs/clover.xml" />
    </report>
    <include>
        <directory suffix=".php">./app/Modules/Contracts</directory>
    </include>
    <exclude>
        <directory>./app/Modules/Contracts/Database/Migrations</directory>
    </exclude>
</coverage>
```

Also add in CI: gate that fails if coverage < 85 (via `php-coveralls` or custom script reading `clover.xml`).

- [ ] **Step 2: Commit**

```bash
git add phpunit.xml
git commit -m "chore(contracts): enable coverage reporting scoped to Contracts module"
```

---

## Task 3 — GitHub Actions CI

**Files:**
- Create: `.github/workflows/tests.yml`

- [ ] **Step 1: Write workflow**

```yaml
name: Tests
on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s
      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, intl, pdo_mysql, redis, xml, simplexml
          coverage: xdebug
          tools: composer

      - name: Install deps
        run: composer install --no-interaction --prefer-dist

      - name: Copy env
        run: cp .env.example .env && php artisan key:generate

      - name: Migrate
        run: php artisan migrate --force
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: root
          REDIS_HOST: 127.0.0.1

      - name: PHPStan
        run: ./vendor/bin/phpstan analyse app/Modules/Contracts --level=8

      - name: Pint check
        run: ./vendor/bin/pint --test app/Modules/Contracts

      - name: Unit tests
        run: php artisan test --testsuite=Unit --coverage-clover=clover.xml
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1

      - name: Feature tests
        run: php artisan test --testsuite=Feature
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1

      - name: Coverage gate (≥85%)
        run: |
          COVERAGE=$(php -r '
              $xml = simplexml_load_file("clover.xml");
              $m = $xml->project->metrics;
              $covered = (int)$m["coveredstatements"];
              $total = (int)$m["statements"];
              echo $total > 0 ? round(($covered/$total)*100, 2) : 0;
          ')
          echo "Coverage: $COVERAGE%"
          php -r "exit($COVERAGE >= 85 ? 0 : 1);"

      - name: Benchmarks
        run: php artisan test tests/Benchmarks
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci(contracts): add GitHub Actions workflow with PHPStan + tests + coverage ≥85 gate + benchmarks"
```

---

## Task 4 — Infection workflow (weekly + manual)

**Files:**
- Create: `.github/workflows/infection.yml`

- [ ] **Step 1: Write workflow**

```yaml
name: Mutation testing
on:
  schedule:
    - cron: '0 5 * * 1'  # lunes 05:00 UTC
  workflow_dispatch:

jobs:
  infection:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s
      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, intl, pdo_mysql, redis
          coverage: xdebug
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --force
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1

      - name: Infection
        run: ./vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=70
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          REDIS_HOST: 127.0.0.1

      - name: Upload report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: infection-report
          path: infection.html
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/infection.yml
git commit -m "ci(contracts): weekly Infection workflow with MSI ≥70 gate"
```

---

## Task 5 — Dry-run reproceso en 3 meses

Ejecuta en local — es verificación manual.

- [ ] **Step 1: Fresh DB**

```bash
php artisan migrate:fresh
```

- [ ] **Step 2: Run reprocess for 3 months**

```bash
php artisan contracts:reprocess --from=201801 --to=201803 --sync
```

- [ ] **Step 3: Verify counters**

```bash
php artisan tinker --execute='
echo "Reprocess runs: " . \Modules\Contracts\Models\ReprocessRun::count() . "\n";
$run = \Modules\Contracts\Models\ReprocessRun::latest()->first();
echo "Last run status: {$run->status}\n";
echo "Total atoms: {$run->total_atoms}\n";
echo "Processed atoms: {$run->processed_atoms}\n";
echo "Total entries: {$run->total_entries}\n";
echo "Failed entries: {$run->failed_entries}\n";
echo "Contracts in DB: " . \Modules\Contracts\Models\Contract::count() . "\n";
echo "Snapshots: " . \Modules\Contracts\Models\ContractSnapshot::count() . "\n";
echo "Parse errors: " . \Modules\Contracts\Models\ParseError::count() . "\n";
'
```

- [ ] **Step 4: Inspect parse_errors for unexpected failures**

```bash
php artisan tinker --execute='
foreach (\Modules\Contracts\Models\ParseError::orderBy("error_code")->get() as $e) {
    echo "[{$e->error_code}] {$e->entry_external_id}: {$e->error_message}\n";
}' | head -30
```
Expected: errores coherentes (entries con XML raro que ya conocemos). Si aparece error nuevo, investiga y añade fixture de regresión.

- [ ] **Step 5: Idempotence spot check**

```bash
php artisan contracts:reprocess --from=201801 --to=201803 --sync
```
Expected: los atoms ya procesados se saltan por hash; 0 filas nuevas.

- [ ] **Step 6: Commit (empty — marcador de checkpoint)**

```bash
git commit --allow-empty -m "chore(contracts): dry-run 3 months reproceso verified"
```

---

## Task 6 — Reproceso total 88 meses + medición

- [ ] **Step 1: Fresh DB**

```bash
php artisan migrate:fresh
```

- [ ] **Step 2: Arranca Horizon**

```bash
php artisan horizon &
HORIZON_PID=$!
```

- [ ] **Step 3: Dispara reproceso total**

```bash
time php artisan contracts:reprocess --from=201801 --to=$(date +%Y%m) --parallel=4
```

- [ ] **Step 4: Monitoriza**

En otra terminal:

```bash
watch -n 5 'php artisan tinker --execute="
\$run = \Modules\Contracts\Models\ReprocessRun::latest()->first();
echo \"Run #{\$run->id} status: {\$run->status}\n\";
echo \"Processed atoms: {\$run->atomRuns()->where(\"status\",\"completed\")->count()} / {\$run->total_atoms}\n\";
echo \"Entries: {\$run->atomRuns()->sum(\"entries_processed\")}\n\";
echo \"Failed atoms: {\$run->atomRuns()->where(\"status\",\"failed\")->count()}\n\";
"'
```

- [ ] **Step 5: Al completar, verifica**

```bash
kill $HORIZON_PID

php artisan tinker --execute='
echo "=== FINAL ===\n";
echo "Contracts: " . \Modules\Contracts\Models\Contract::count() . "\n";
echo "Lots: " . \Modules\Contracts\Models\ContractLot::count() . "\n";
echo "Awards: " . \Modules\Contracts\Models\Award::count() . "\n";
echo "Organizations: " . \Modules\Contracts\Models\Organization::count() . "\n";
echo "Companies: " . \Modules\Contracts\Models\Company::count() . "\n";
echo "Notices: " . \Modules\Contracts\Models\ContractNotice::count() . "\n";
echo "Modifications: " . \Modules\Contracts\Models\ContractModification::count() . "\n";
echo "Snapshots: " . \Modules\Contracts\Models\ContractSnapshot::count() . "\n";
echo "Criteria: " . \Modules\Contracts\Models\AwardingCriterion::count() . "\n";
echo "Parse errors: " . \Modules\Contracts\Models\ParseError::count() . "\n";
'
```

Gate: total entries < 600k, completion time < 10 min (escala con `--parallel=N` si excede).

- [ ] **Step 6: Spot-check del contrato CG-09/17**

```bash
php artisan tinker --execute='
$c = \Modules\Contracts\Models\Contract::where("external_id", "like", "%1892425%")->first();
if ($c) {
    echo "Contract: {$c->expediente} status {$c->status_code}\n";
    echo "Snapshots: " . $c->snapshots()->count() . "\n";
    foreach ($c->snapshots as $s) {
        echo "  {$s->entry_updated_at} — {$s->status_code}\n";
    }
    echo "Notices: " . $c->notices()->count() . "\n";
    foreach ($c->notices as $n) {
        echo "  {$n->issue_date} {$n->notice_type_code}\n";
    }
} else {
    echo "NOT FOUND\n";
}
'
```
Expected: ≥2 snapshots (PUB + RES), ≥3 notices incluyendo DOC_CAN_ADJ y DOC_FORM.

- [ ] **Step 7: Commit (empty checkpoint)**

```bash
git commit --allow-empty -m "chore(contracts): full reproceso 88 months validated end-to-end"
```

---

## Task 7 — Run Infection local + verificar MSI

- [ ] **Step 1: Run Infection**

```bash
./vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=70
```

- [ ] **Step 2: Inspect HTML report**

```bash
open infection.html  # (macOS) o start infection.html (Windows)
```
Revisa mutantes escapados. Si un mutante razonable escapó, escribe un test que lo mate o marca con `@infection-ignore` si es trivial (getter, toString).

- [ ] **Step 3: Si MSI < 70, añadir tests hasta superar**

Iterar: mira los mutantes escapados, escribe un test que cubra el caso, vuelve a correr Infection.

- [ ] **Step 4: Commit tests de mutation kill**

```bash
git add tests/
git commit -m "test(contracts): add tests to kill escaped mutants (MSI ≥70)"
```

---

## Task 8 — Benchmarks con BD llena + ajustes

- [ ] **Step 1: Run con DB llena (tras reproceso total)**

```bash
php artisan test tests/Benchmarks
```

- [ ] **Step 2: Si algún benchmark falla, diagnostica**

```bash
php artisan tinker --execute='
\DB::enableQueryLog();
\Modules\Contracts\Models\Contract::query()
    ->with(["lots.awards.company", "notices", "modifications"])
    ->limit(25)
    ->get();
var_dump(count(\DB::getQueryLog()));
'
```
Si hay N+1, añade eager loading específico o índice.

- [ ] **Step 3: Commit eventuales ajustes de índices/queries**

```bash
git add -A
git commit -m "perf(contracts): tune indices/eager loading for benchmarks under full-data load"
```

---

## Task 9 — Final PR merge

- [ ] **Step 1: Push + PR**

```bash
git push -u origin feature/contracts-v2-integration
gh pr create --title "contracts v2 — Phase 1.5 integration (Spec 1 CIERRE)" --body "$(cat <<'EOF'
## Summary
- CI workflow: PHPStan + Pint + tests + coverage gate ≥85 + benchmarks.
- Infection workflow weekly + workflow_dispatch, MSI ≥70 gate.
- Dry-run 3 meses verde.
- Reproceso total 88 meses completado (<10 min, 4 workers).
- Idempotencia validada: 2do reproceso → 0 filas nuevas.
- Spot-check CG-09/17: múltiples snapshots + notices completos.
- Infection MSI local ≥70%.
- Benchmarks bajo threshold con BD llena.

## Test plan
- [x] CI workflow verde
- [x] Coverage ≥85%
- [x] Infection MSI ≥70%
- [x] Reproceso 88 meses <10 min
- [x] Idempotencia 0 diff
- [x] Benchmarks warm <300ms, cold <700ms

## Spec 1 completa
Cierra la fase backend del proyecto Contracts v2. Siguientes specs:
- **Spec 2**: Ficha de contrato (frontend Vue) — consume ahora la API rica.
- **Spec 3**: Ficha de empresa (frontend Vue) — nueva ruta pública.
- **Spec 4 (futuro)**: Snapshot history exploitation + fraud detection panel.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 2: Merge a main tras aprobación**

```bash
gh pr merge --squash --delete-branch
git checkout main && git pull
```

- [ ] **Step 3: Tag release**

```bash
git tag -a contracts-v2.0.0 -m "Contracts Backend v2 — Spec 1 complete"
git push origin contracts-v2.0.0
```

---

## Cierre del Spec 1

Con 1.5 mergeada, el Spec 1 está completo. Actualiza el índice del plan (`docs/superpowers/plans/2026-04-24-contracts-backend-v2/README.md`) marcando todas las fases como ✅ y añade la línea "Spec 1 completado: 2026-MM-DD" en la sección de estado.

A partir de aquí, inicia brainstorming del Spec 2 (Ficha de contrato frontend) usando `superpowers:brainstorming` con el contexto del spec 1 cerrado.
