# Phase 1.0 — Foundation (Migrations + Models + DTOs + Factories)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`.

**Goal:** Crear todas las tablas y modelos que soportan el resto de fases. Bloqueante.

**Architecture:** 12 migraciones Laravel estándar. 13 modelos Eloquent (7 nuevos, 6 modificados). 11 DTOs readonly PHP 8.4. Factories para tests.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8.

**Branch:** `feature/contracts-v2-foundation`. **Worktree:** `wt-1.0-foundation`.

**Gate (antes de merge):**
- `php artisan migrate:fresh` corre limpio.
- `./vendor/bin/phpstan analyse app/Modules/Contracts --level=8` verde.
- Todas las factories instanciables vía `::factory()->create()`.
- Tests unit de modelos (relations, casts) verdes.

---

## Task 1 — Migration: create `contract_lots` table

**Files:**
- Create: `database/migrations/2026_04_25_000001_create_contract_lots_table.php`
- Test: `tests/Feature/Contracts/Migrations/ContractLotsSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Contracts/Migrations/ContractLotsSchemaTest.php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractLotsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_lots_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_lots'));
        $cols = ['id','contract_id','lot_number','title','description',
            'tipo_contrato_code','subtipo_contrato_code','cpv_codes',
            'budget_with_tax','budget_without_tax','estimated_value',
            'duration','duration_unit','start_date','end_date',
            'nuts_code','lugar_ejecucion','options_description',
            'created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_lots', $c), "Missing col {$c}");
        }
    }

    public function test_contract_lots_unique_contract_and_lot_number(): void
    {
        $idx = collect(Schema::getIndexes('contract_lots'))
            ->where('unique', true)
            ->pluck('columns')
            ->flatten()
            ->sort()
            ->values()
            ->all();
        $this->assertContains(['contract_id','lot_number'], collect(Schema::getIndexes('contract_lots'))->where('unique', true)->pluck('columns')->all());
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
php artisan test tests/Feature/Contracts/Migrations/ContractLotsSchemaTest.php
```
Expected: FAIL `contract_lots table does not exist`.

- [ ] **Step 3: Create migration**

```php
<?php
// database/migrations/2026_04_25_000001_create_contract_lots_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->unsignedInteger('lot_number');
            $t->string('title', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('tipo_contrato_code', 10)->nullable();
            $t->string('subtipo_contrato_code', 10)->nullable();
            $t->json('cpv_codes')->nullable();
            $t->decimal('budget_with_tax', 15, 2)->nullable();
            $t->decimal('budget_without_tax', 15, 2)->nullable();
            $t->decimal('estimated_value', 15, 2)->nullable();
            $t->decimal('duration', 8, 2)->nullable();
            $t->string('duration_unit', 10)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('nuts_code', 10)->nullable();
            $t->string('lugar_ejecucion', 255)->nullable();
            $t->text('options_description')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'lot_number']);
            $t->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_lots');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

```bash
php artisan test tests/Feature/Contracts/Migrations/ContractLotsSchemaTest.php
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000001_create_contract_lots_table.php tests/Feature/Contracts/Migrations/ContractLotsSchemaTest.php
git commit -m "feat(contracts): add contract_lots migration + schema test"
```

---

## Task 2 — Migration: modify `contracts` (v2 columns)

**Files:**
- Create: `database/migrations/2026_04_25_000002_modify_contracts_table_for_v2.php`
- Test: `tests/Feature/Contracts/Migrations/ContractsV2ColumnsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_has_v2_columns(): void
    {
        $new = ['buyer_profile_uri','activity_code','mix_contract_indicator',
            'funding_program_code','over_threshold_indicator','national_legislation_code',
            'received_appeal_quantity','snapshot_updated_at','annulled_at'];
        foreach ($new as $c) {
            $this->assertTrue(Schema::hasColumn('contracts', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
php artisan test tests/Feature/Contracts/Migrations/ContractsV2ColumnsTest.php
```

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->string('buyer_profile_uri', 500)->nullable()->after('link');
            $t->string('activity_code', 10)->nullable()->after('buyer_profile_uri');
            $t->boolean('mix_contract_indicator')->nullable()->after('activity_code');
            $t->string('funding_program_code', 20)->nullable()->after('mix_contract_indicator');
            $t->boolean('over_threshold_indicator')->nullable()->after('funding_program_code');
            $t->string('national_legislation_code', 20)->nullable()->after('over_threshold_indicator');
            $t->unsignedInteger('received_appeal_quantity')->nullable()->after('national_legislation_code');
            $t->timestamp('snapshot_updated_at')->nullable()->after('received_appeal_quantity');
            $t->timestamp('annulled_at')->nullable()->after('snapshot_updated_at');

            $t->index(['status_code', 'snapshot_updated_at'], 'contracts_status_snapshot_idx');
            $t->index(['tipo_contrato_code', 'status_code'], 'contracts_tipo_status_idx');
            $t->index('annulled_at', 'contracts_annulled_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->dropIndex('contracts_status_snapshot_idx');
            $t->dropIndex('contracts_tipo_status_idx');
            $t->dropIndex('contracts_annulled_idx');
            $t->dropColumn(['buyer_profile_uri','activity_code','mix_contract_indicator',
                'funding_program_code','over_threshold_indicator','national_legislation_code',
                'received_appeal_quantity','snapshot_updated_at','annulled_at']);
        });
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

```bash
php artisan test tests/Feature/Contracts/Migrations/ContractsV2ColumnsTest.php
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000002_modify_contracts_table_for_v2.php tests/Feature/Contracts/Migrations/ContractsV2ColumnsTest.php
git commit -m "feat(contracts): add v2 columns to contracts table"
```

---

## Task 3 — Migration: modify `awards` (switch FK to contract_lot_id + new columns)

**Files:**
- Create: `database/migrations/2026_04_25_000003_modify_awards_table_for_v2.php`
- Test: `tests/Feature/Contracts/Migrations/AwardsV2ColumnsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AwardsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_has_contract_lot_id_and_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('awards', 'contract_lot_id'));
        $this->assertFalse(Schema::hasColumn('awards', 'contract_id'));
        $new = ['description','start_date','lower_tender_amount','higher_tender_amount','smes_received_tender_quantity'];
        foreach ($new as $c) {
            $this->assertTrue(Schema::hasColumn('awards', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
php artisan test tests/Feature/Contracts/Migrations/AwardsV2ColumnsTest.php
```

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Since we're doing wipe+reproceso (no data to preserve), drop+recreate FK cleanly.
        Schema::table('awards', function (Blueprint $t) {
            if (Schema::hasColumn('awards', 'contract_id')) {
                $t->dropForeign(['contract_id']);
                $t->dropColumn('contract_id');
            }
            $t->foreignId('contract_lot_id')->after('id')->constrained('contract_lots')->cascadeOnDelete();
            $t->text('description')->nullable()->after('amount_without_tax');
            $t->date('start_date')->nullable()->after('award_date');
            $t->decimal('lower_tender_amount', 15, 2)->nullable()->after('start_date');
            $t->decimal('higher_tender_amount', 15, 2)->nullable()->after('lower_tender_amount');
            $t->unsignedInteger('smes_received_tender_quantity')->nullable()->after('num_offers');

            $t->unique(['contract_lot_id', 'company_id'], 'awards_lot_company_unique');
            $t->index(['company_id', 'award_date'], 'awards_company_award_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $t) {
            $t->dropIndex('awards_company_award_date_idx');
            $t->dropUnique('awards_lot_company_unique');
            $t->dropForeign(['contract_lot_id']);
            $t->dropColumn(['contract_lot_id','description','start_date',
                'lower_tender_amount','higher_tender_amount','smes_received_tender_quantity']);
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
        });
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

```bash
php artisan test tests/Feature/Contracts/Migrations/AwardsV2ColumnsTest.php
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000003_modify_awards_table_for_v2.php tests/Feature/Contracts/Migrations/AwardsV2ColumnsTest.php
git commit -m "feat(contracts): migrate awards to contract_lot_id + v2 columns"
```

---

## Task 4 — Migration: create `awarding_criteria` table

**Files:**
- Create: `database/migrations/2026_04_25_000004_create_awarding_criteria_table.php`
- Test: `tests/Feature/Contracts/Migrations/AwardingCriteriaSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AwardingCriteriaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_awarding_criteria_table_exists_with_columns(): void
    {
        $this->assertTrue(Schema::hasTable('awarding_criteria'));
        $cols = ['id','contract_lot_id','type_code','subtype_code','description','note','weight_numeric','sort_order','created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('awarding_criteria', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

```bash
php artisan test tests/Feature/Contracts/Migrations/AwardingCriteriaSchemaTest.php
```

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('awarding_criteria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_lot_id')->constrained('contract_lots')->cascadeOnDelete();
            $t->string('type_code', 5);
            $t->string('subtype_code', 10)->nullable();
            $t->text('description');
            $t->text('note')->nullable();
            $t->decimal('weight_numeric', 8, 2)->nullable();
            $t->unsignedInteger('sort_order');
            $t->timestamps();
            $t->unique(['contract_lot_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awarding_criteria');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000004_create_awarding_criteria_table.php tests/Feature/Contracts/Migrations/AwardingCriteriaSchemaTest.php
git commit -m "feat(contracts): add awarding_criteria migration"
```

---

## Task 5 — Migration: unique on `contract_notices` + `contract_documents`

**Files:**
- Create: `database/migrations/2026_04_25_000005_add_unique_constraints_to_notices_and_documents.php`
- Test: `tests/Feature/Contracts/Migrations/NoticesDocumentsUniqueTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NoticesDocumentsUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_notices_has_idempotency_unique(): void
    {
        $uniques = collect(Schema::getIndexes('contract_notices'))->where('unique', true)->pluck('columns')->all();
        $this->assertContains(['contract_id','notice_type_code','issue_date'], $uniques);
    }

    public function test_contract_documents_has_uri_unique(): void
    {
        $uniques = collect(Schema::getIndexes('contract_documents'))->where('unique', true)->pluck('columns')->all();
        $this->assertContains(['contract_id','uri'], $uniques);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_notices', function (Blueprint $t) {
            $t->unique(['contract_id', 'notice_type_code', 'issue_date'], 'contract_notices_idempotency_unique');
            $t->index(['contract_id', 'issue_date'], 'contract_notices_issue_date_idx');
        });
        Schema::table('contract_documents', function (Blueprint $t) {
            $t->unique(['contract_id', 'uri'], 'contract_documents_uri_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contract_notices', function (Blueprint $t) {
            $t->dropUnique('contract_notices_idempotency_unique');
            $t->dropIndex('contract_notices_issue_date_idx');
        });
        Schema::table('contract_documents', function (Blueprint $t) {
            $t->dropUnique('contract_documents_uri_unique');
        });
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000005_add_unique_constraints_to_notices_and_documents.php tests/Feature/Contracts/Migrations/NoticesDocumentsUniqueTest.php
git commit -m "feat(contracts): add idempotency unique to notices + documents"
```

---

## Task 6 — Migration: create `contract_modifications`

**Files:**
- Create: `database/migrations/2026_04_25_000006_create_contract_modifications_table.php`
- Test: `tests/Feature/Contracts/Migrations/ContractModificationsSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractModificationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_modifications'));
        $cols = ['id','contract_id','type','issue_date','effective_date','description','amount_delta','new_end_date','related_notice_id','created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_modifications', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_modifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->enum('type', ['modification','extension','cancellation','assignment','annulment']);
            $t->date('issue_date');
            $t->date('effective_date')->nullable();
            $t->text('description')->nullable();
            $t->decimal('amount_delta', 15, 2)->nullable();
            $t->date('new_end_date')->nullable();
            $t->foreignId('related_notice_id')->nullable()->constrained('contract_notices')->nullOnDelete();
            $t->timestamps();
            $t->unique(['contract_id','type','issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_modifications');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000006_create_contract_modifications_table.php tests/Feature/Contracts/Migrations/ContractModificationsSchemaTest.php
git commit -m "feat(contracts): add contract_modifications migration"
```

---

## Task 7 — Migration: create `contract_snapshots`

**Files:**
- Create: `database/migrations/2026_04_25_000007_create_contract_snapshots_table.php`
- Test: `tests/Feature/Contracts/Migrations/ContractSnapshotsSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractSnapshotsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contract_snapshots'));
        $cols = ['id','contract_id','entry_updated_at','status_code','content_hash','payload','source_atom','ingested_at','created_at','updated_at'];
        foreach ($cols as $c) {
            $this->assertTrue(Schema::hasColumn('contract_snapshots', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->timestamp('entry_updated_at');
            $t->string('status_code', 5);
            $t->char('content_hash', 40);
            $t->json('payload')->nullable();
            $t->string('source_atom', 500)->nullable();
            $t->timestamp('ingested_at');
            $t->timestamps();
            $t->unique(['contract_id', 'entry_updated_at']);
            $t->index(['contract_id', 'entry_updated_at'], 'snapshots_contract_updated_idx');
            $t->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_snapshots');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000007_create_contract_snapshots_table.php tests/Feature/Contracts/Migrations/ContractSnapshotsSchemaTest.php
git commit -m "feat(contracts): add contract_snapshots migration (nivel 3 scaffold)"
```

---

## Task 8 — Migration: `reprocess_runs` + `reprocess_atom_runs`

**Files:**
- Create: `database/migrations/2026_04_25_000008_create_reprocess_runs_tables.php`
- Test: `tests/Feature/Contracts/Migrations/ReprocessRunsSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReprocessRunsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocess_runs_table(): void
    {
        $this->assertTrue(Schema::hasTable('reprocess_runs'));
        foreach (['id','name','status','started_at','finished_at','total_atoms','processed_atoms','total_entries','failed_entries','config'] as $c) {
            $this->assertTrue(Schema::hasColumn('reprocess_runs', $c), "Missing {$c}");
        }
    }

    public function test_reprocess_atom_runs_table(): void
    {
        $this->assertTrue(Schema::hasTable('reprocess_atom_runs'));
        foreach (['id','reprocess_run_id','atom_path','atom_hash','status','started_at','finished_at','entries_processed','entries_failed','error_message'] as $c) {
            $this->assertTrue(Schema::hasColumn('reprocess_atom_runs', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reprocess_runs', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->nullable();
            $t->enum('status', ['pending','running','completed','failed','cancelled'])->default('pending');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('total_atoms')->nullable();
            $t->unsignedInteger('processed_atoms')->default(0);
            $t->unsignedInteger('total_entries')->default(0);
            $t->unsignedInteger('failed_entries')->default(0);
            $t->json('config');
            $t->timestamps();
        });

        Schema::create('reprocess_atom_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reprocess_run_id')->constrained('reprocess_runs')->cascadeOnDelete();
            $t->string('atom_path', 500);
            $t->char('atom_hash', 40);
            $t->enum('status', ['pending','running','completed','failed'])->default('pending');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedInteger('entries_processed')->default(0);
            $t->unsignedInteger('entries_failed')->default(0);
            $t->text('error_message')->nullable();
            $t->timestamps();
            $t->index(['reprocess_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reprocess_atom_runs');
        Schema::dropIfExists('reprocess_runs');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000008_create_reprocess_runs_tables.php tests/Feature/Contracts/Migrations/ReprocessRunsSchemaTest.php
git commit -m "feat(contracts): add reprocess_runs + reprocess_atom_runs migrations"
```

---

## Task 9 — Migration: `parse_errors`

**Files:**
- Create: `database/migrations/2026_04_25_000009_create_parse_errors_table.php`
- Test: `tests/Feature/Contracts/Migrations/ParseErrorsSchemaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ParseErrorsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_errors_table(): void
    {
        $this->assertTrue(Schema::hasTable('parse_errors'));
        foreach (['id','reprocess_atom_run_id','atom_path','entry_external_id','error_code','error_message','raw_fragment'] as $c) {
            $this->assertTrue(Schema::hasColumn('parse_errors', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parse_errors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reprocess_atom_run_id')->nullable()->constrained('reprocess_atom_runs')->nullOnDelete();
            $t->string('atom_path', 500);
            $t->string('entry_external_id', 500)->nullable();
            $t->string('error_code', 50);
            $t->text('error_message');
            $t->text('raw_fragment')->nullable();
            $t->timestamps();
            $t->index('error_code');
            $t->index('reprocess_atom_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parse_errors');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000009_create_parse_errors_table.php tests/Feature/Contracts/Migrations/ParseErrorsSchemaTest.php
git commit -m "feat(contracts): add parse_errors migration"
```

---

## Task 10 — Migration: organizations v2 columns

**Files:**
- Create: `database/migrations/2026_04_25_000010_add_v2_columns_to_organizations.php`
- Test: `tests/Feature/Contracts/Migrations/OrganizationsV2ColumnsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrganizationsV2ColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_has_v2_columns(): void
    {
        foreach (['buyer_profile_uri','activity_code','platform_id'] as $c) {
            $this->assertTrue(Schema::hasColumn('organizations', $c), "Missing {$c}");
        }
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->string('buyer_profile_uri', 500)->nullable()->after('name');
            $t->string('activity_code', 10)->nullable()->after('buyer_profile_uri');
            $t->string('platform_id', 50)->nullable()->after('activity_code');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->dropColumn(['buyer_profile_uri', 'activity_code', 'platform_id']);
        });
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000010_add_v2_columns_to_organizations.php tests/Feature/Contracts/Migrations/OrganizationsV2ColumnsTest.php
git commit -m "feat(contracts): add v2 columns to organizations"
```

---

## Task 11 — Migration: fulltext index on contracts

**Files:**
- Create: `database/migrations/2026_04_25_000011_add_fulltext_to_contracts.php`
- Test: `tests/Feature/Contracts/Migrations/ContractsFulltextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractsFulltextTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_has_fulltext_index_on_objeto_and_expediente(): void
    {
        $rows = DB::select("SHOW INDEX FROM contracts WHERE Index_type = 'FULLTEXT'");
        $indexNames = array_unique(array_column($rows, 'Key_name'));
        $this->assertContains('contracts_objeto_expediente_fulltext', $indexNames);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE contracts ADD FULLTEXT contracts_objeto_expediente_fulltext (objeto, expediente)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE contracts DROP INDEX contracts_objeto_expediente_fulltext');
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000011_add_fulltext_to_contracts.php tests/Feature/Contracts/Migrations/ContractsFulltextTest.php
git commit -m "feat(contracts): add FULLTEXT index to contracts(objeto, expediente)"
```

---

## Task 12 — Migration: legacy JSON column cleanup (`criterios_adjudicacion` on contracts)

**Files:**
- Create: `database/migrations/2026_04_25_000012_drop_legacy_criterios_from_contracts.php`
- Test: `tests/Feature/Contracts/Migrations/ContractsLegacyCleanupTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Feature\Contracts\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractsLegacyCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_criterios_adjudicacion_column_is_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('contracts', 'criterios_adjudicacion'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            if (Schema::hasColumn('contracts', 'criterios_adjudicacion')) {
                $t->dropColumn('criterios_adjudicacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $t) {
            $t->json('criterios_adjudicacion')->nullable();
        });
    }
};
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_25_000012_drop_legacy_criterios_from_contracts.php tests/Feature/Contracts/Migrations/ContractsLegacyCleanupTest.php
git commit -m "refactor(contracts): drop legacy criterios_adjudicacion column"
```

---

## Task 13 — Verify `migrate:fresh` runs clean (checkpoint)

- [ ] **Step 1: Drop and re-migrate**

```bash
php artisan migrate:fresh
```
Expected: output ends with `Running: ... — Done`. No errors.

- [ ] **Step 2: Confirm all 12 new migrations ran**

```bash
php artisan migrate:status | grep 2026_04_25
```
Expected: 12 rows, all with `[Ran]` column.

- [ ] **Step 3: Commit checkpoint (empty commit, marks gate)**

```bash
git commit --allow-empty -m "chore(contracts): checkpoint — all v2 migrations apply cleanly"
```

---

## Task 14 — Model: `ContractLot`

**Files:**
- Create: `app/Modules/Contracts/Models/ContractLot.php`
- Create: `database/factories/Modules/Contracts/ContractLotFactory.php`
- Test: `tests/Unit/Contracts/Models/ContractLotTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class ContractLotTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_lot(): void
    {
        $lot = ContractLot::factory()->create();
        $this->assertNotNull($lot->id);
        $this->assertInstanceOf(Contract::class, $lot->contract);
    }

    public function test_casts(): void
    {
        $lot = ContractLot::factory()->create([
            'cpv_codes' => ['12345678', '87654321'],
            'start_date' => '2026-01-01',
        ]);
        $this->assertIsArray($lot->cpv_codes);
        $this->assertEquals('2026-01-01', $lot->start_date->format('Y-m-d'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL (model not found)**

```bash
php artisan test tests/Unit/Contracts/Models/ContractLotTest.php
```

- [ ] **Step 3: Create the model**

```php
<?php
// app/Modules/Contracts/Models/ContractLot.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id','lot_number','title','description','tipo_contrato_code',
        'subtipo_contrato_code','cpv_codes','budget_with_tax','budget_without_tax',
        'estimated_value','duration','duration_unit','start_date','end_date',
        'nuts_code','lugar_ejecucion','options_description',
    ];

    protected $casts = [
        'cpv_codes' => 'array',
        'budget_with_tax' => 'decimal:2',
        'budget_without_tax' => 'decimal:2',
        'estimated_value' => 'decimal:2',
        'duration' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AwardingCriterion::class)->orderBy('sort_order');
    }

    protected static function newFactory(): \Database\Factories\Modules\Contracts\ContractLotFactory
    {
        return \Database\Factories\Modules\Contracts\ContractLotFactory::new();
    }
}
```

- [ ] **Step 4: Create the factory**

```php
<?php
// database/factories/Modules/Contracts/ContractLotFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;

class ContractLotFactory extends Factory
{
    protected $model = ContractLot::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'lot_number' => $this->faker->numberBetween(1, 10),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'tipo_contrato_code' => $this->faker->randomElement(['1','2','3']),
            'cpv_codes' => [$this->faker->numerify('########')],
            'budget_with_tax' => $this->faker->randomFloat(2, 1000, 500000),
            'budget_without_tax' => $this->faker->randomFloat(2, 800, 400000),
            'estimated_value' => $this->faker->randomFloat(2, 1000, 500000),
            'duration' => $this->faker->numberBetween(1, 36),
            'duration_unit' => 'MON',
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'nuts_code' => 'ES' . $this->faker->numerify('###'),
            'lugar_ejecucion' => $this->faker->city(),
        ];
    }
}
```

- [ ] **Step 5: Run test, expect PASS**

```bash
php artisan test tests/Unit/Contracts/Models/ContractLotTest.php
```

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Contracts/Models/ContractLot.php database/factories/Modules/Contracts/ContractLotFactory.php tests/Unit/Contracts/Models/ContractLotTest.php
git commit -m "feat(contracts): add ContractLot model + factory + unit test"
```

---

## Task 15 — Model: `AwardingCriterion`

**Files:**
- Create: `app/Modules/Contracts/Models/AwardingCriterion.php`
- Create: `database/factories/Modules/Contracts/AwardingCriterionFactory.php`
- Test: `tests/Unit/Contracts/Models/AwardingCriterionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class AwardingCriterionTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_lot(): void
    {
        $c = AwardingCriterion::factory()->create();
        $this->assertInstanceOf(ContractLot::class, $c->contractLot);
    }

    public function test_weight_casts_decimal(): void
    {
        $c = AwardingCriterion::factory()->create(['weight_numeric' => 70.5]);
        $this->assertEquals('70.50', $c->weight_numeric);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create model + factory**

```php
<?php
// app/Modules/Contracts/Models/AwardingCriterion.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AwardingCriterion extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_lot_id','type_code','subtype_code','description','note','weight_numeric','sort_order',
    ];

    protected $casts = [
        'weight_numeric' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function contractLot(): BelongsTo
    {
        return $this->belongsTo(ContractLot::class);
    }

    protected static function newFactory(): \Database\Factories\Modules\Contracts\AwardingCriterionFactory
    {
        return \Database\Factories\Modules\Contracts\AwardingCriterionFactory::new();
    }
}
```

```php
<?php
// database/factories/Modules/Contracts/AwardingCriterionFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\AwardingCriterion;
use Modules\Contracts\Models\ContractLot;

class AwardingCriterionFactory extends Factory
{
    protected $model = AwardingCriterion::class;

    public function definition(): array
    {
        return [
            'contract_lot_id' => ContractLot::factory(),
            'type_code' => $this->faker->randomElement(['OBJ','SUBJ']),
            'subtype_code' => '1',
            'description' => $this->faker->sentence(),
            'note' => $this->faker->paragraph(),
            'weight_numeric' => $this->faker->randomFloat(2, 1, 100),
            'sort_order' => $this->faker->unique()->numberBetween(1, 20),
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/AwardingCriterion.php database/factories/Modules/Contracts/AwardingCriterionFactory.php tests/Unit/Contracts/Models/AwardingCriterionTest.php
git commit -m "feat(contracts): add AwardingCriterion model + factory"
```

---

## Task 16 — Modify `Contract` model (v2 columns + relations + scopes)

**Files:**
- Modify: `app/Modules/Contracts/Models/Contract.php`
- Test: `tests/Unit/Contracts/Models/ContractV2Test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractLot;
use Modules\Contracts\Models\ContractModification;
use Modules\Contracts\Models\ContractSnapshot;
use Tests\TestCase;

class ContractV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_has_lots_relation(): void
    {
        $c = Contract::factory()->create();
        ContractLot::factory()->for($c)->create(['lot_number' => 1]);
        $this->assertCount(1, $c->fresh()->lots);
    }

    public function test_has_modifications_relation(): void
    {
        $c = Contract::factory()->create();
        ContractModification::factory()->for($c)->create();
        $this->assertCount(1, $c->fresh()->modifications);
    }

    public function test_has_snapshots_relation(): void
    {
        $c = Contract::factory()->create();
        ContractSnapshot::factory()->for($c)->create();
        $this->assertCount(1, $c->fresh()->snapshots);
    }

    public function test_casts_v2_columns(): void
    {
        $c = Contract::factory()->create([
            'mix_contract_indicator' => true,
            'over_threshold_indicator' => false,
            'snapshot_updated_at' => '2026-01-01 10:00:00',
            'annulled_at' => '2026-02-01 11:00:00',
        ]);
        $this->assertTrue($c->mix_contract_indicator);
        $this->assertFalse($c->over_threshold_indicator);
        $this->assertNotNull($c->snapshot_updated_at);
        $this->assertNotNull($c->annulled_at);
    }

    public function test_scope_not_annulled(): void
    {
        Contract::factory()->create(['annulled_at' => now()]);
        Contract::factory()->create(['annulled_at' => null]);
        $this->assertCount(1, Contract::notAnnulled()->get());
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Update model**

```php
<?php
// app/Modules/Contracts/Models/Contract.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id','expediente','link','buyer_profile_uri','activity_code',
        'status_code','objeto','tipo_contrato_code','subtipo_contrato_code',
        'importe_sin_iva','importe_con_iva','valor_estimado',
        'procedimiento_code','urgencia_code','cpv_codes','comunidad_autonoma','nuts_code',
        'lugar_ejecucion','fecha_presentacion_limite','duracion','duracion_unidad',
        'fecha_inicio','fecha_fin','submission_method_code','contracting_system_code',
        'fecha_disponibilidad_docs','hora_presentacion_limite',
        'garantia_tipo_code','garantia_porcentaje','idioma','opciones_descripcion',
        'organization_id','synced_at','mix_contract_indicator','funding_program_code',
        'over_threshold_indicator','national_legislation_code','received_appeal_quantity',
        'snapshot_updated_at','annulled_at',
    ];

    protected $casts = [
        'cpv_codes' => 'array',
        'importe_sin_iva' => 'decimal:2',
        'importe_con_iva' => 'decimal:2',
        'valor_estimado' => 'decimal:2',
        'duracion' => 'decimal:2',
        'garantia_porcentaje' => 'decimal:2',
        'fecha_presentacion_limite' => 'datetime',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_disponibilidad_docs' => 'datetime',
        'synced_at' => 'datetime',
        'mix_contract_indicator' => 'boolean',
        'over_threshold_indicator' => 'boolean',
        'snapshot_updated_at' => 'datetime',
        'annulled_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ContractLot::class)->orderBy('lot_number');
    }

    public function notices(): HasMany
    {
        return $this->hasMany(ContractNotice::class)->orderBy('issue_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class);
    }

    public function modifications(): HasMany
    {
        return $this->hasMany(ContractModification::class)->orderBy('issue_date');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ContractSnapshot::class)->orderBy('entry_updated_at');
    }

    public function scopeNotAnnulled(Builder $q): Builder
    {
        return $q->whereNull('annulled_at');
    }

    public function scopeStatus(Builder $q, string $code): Builder
    {
        return $q->where('status_code', $code);
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/Contract.php tests/Unit/Contracts/Models/ContractV2Test.php
git commit -m "feat(contracts): add v2 relations + casts + scopes to Contract model"
```

---

## Task 17 — Modify `Award` model (contract_lot_id + new fields)

**Files:**
- Modify: `app/Modules/Contracts/Models/Award.php`
- Test: `tests/Unit/Contracts/Models/AwardV2Test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\ContractLot;
use Tests\TestCase;

class AwardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_lot(): void
    {
        $lot = ContractLot::factory()->create();
        $a = Award::factory()->for($lot, 'contractLot')->create();
        $this->assertSame($lot->id, $a->contract_lot_id);
    }

    public function test_casts_amounts_and_dates(): void
    {
        $a = Award::factory()->create([
            'lower_tender_amount' => 1000.50,
            'higher_tender_amount' => 5000.00,
            'start_date' => '2026-03-01',
            'sme_awarded' => true,
        ]);
        $this->assertEquals('1000.50', $a->lower_tender_amount);
        $this->assertEquals('5000.00', $a->higher_tender_amount);
        $this->assertTrue($a->sme_awarded);
        $this->assertEquals('2026-03-01', $a->start_date->format('Y-m-d'));
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Update model + factory**

```php
<?php
// app/Modules/Contracts/Models/Award.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_lot_id','company_id','amount','amount_without_tax','description',
        'procedure_type','urgency','award_date','start_date','formalization_date',
        'contract_number','sme_awarded','num_offers','smes_received_tender_quantity',
        'result_code','lower_tender_amount','higher_tender_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_without_tax' => 'decimal:2',
        'lower_tender_amount' => 'decimal:2',
        'higher_tender_amount' => 'decimal:2',
        'award_date' => 'date',
        'start_date' => 'date',
        'formalization_date' => 'date',
        'sme_awarded' => 'boolean',
    ];

    public function contractLot(): BelongsTo
    {
        return $this->belongsTo(ContractLot::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

Then update the existing `AwardFactory` to use `contract_lot_id` instead of `contract_id`:

```php
<?php
// database/factories/Modules/Contracts/AwardFactory.php (update if exists)
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Award;
use Modules\Contracts\Models\Company;
use Modules\Contracts\Models\ContractLot;

class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'contract_lot_id' => ContractLot::factory(),
            'company_id' => Company::factory(),
            'amount' => $this->faker->randomFloat(2, 1000, 500000),
            'amount_without_tax' => $this->faker->randomFloat(2, 800, 400000),
            'description' => $this->faker->sentence(),
            'procedure_type' => '9',
            'urgency' => '1',
            'award_date' => $this->faker->date(),
            'start_date' => $this->faker->date(),
            'formalization_date' => $this->faker->date(),
            'contract_number' => $this->faker->regexify('[A-Z0-9-]{10}'),
            'sme_awarded' => $this->faker->boolean(),
            'num_offers' => $this->faker->numberBetween(1, 10),
            'smes_received_tender_quantity' => $this->faker->numberBetween(0, 10),
            'result_code' => '8',
            'lower_tender_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'higher_tender_amount' => $this->faker->randomFloat(2, 10000, 100000),
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/Award.php database/factories/Modules/Contracts/AwardFactory.php tests/Unit/Contracts/Models/AwardV2Test.php
git commit -m "feat(contracts): migrate Award to contract_lot_id + v2 columns"
```

---

## Task 18 — Model: `ContractModification`

**Files:**
- Create: `app/Modules/Contracts/Models/ContractModification.php`
- Create: `database/factories/Modules/Contracts/ContractModificationFactory.php`
- Test: `tests/Unit/Contracts/Models/ContractModificationTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractModification;
use Tests\TestCase;

class ContractModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_contract(): void
    {
        $m = ContractModification::factory()->create();
        $this->assertInstanceOf(Contract::class, $m->contract);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create model + factory**

```php
<?php
// app/Modules/Contracts/Models/ContractModification.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractModification extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id','type','issue_date','effective_date','description',
        'amount_delta','new_end_date','related_notice_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'effective_date' => 'date',
        'new_end_date' => 'date',
        'amount_delta' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function relatedNotice(): BelongsTo
    {
        return $this->belongsTo(ContractNotice::class, 'related_notice_id');
    }
}
```

```php
<?php
// database/factories/Modules/Contracts/ContractModificationFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractModification;

class ContractModificationFactory extends Factory
{
    protected $model = ContractModification::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'type' => $this->faker->randomElement(['modification','extension','cancellation','assignment','annulment']),
            'issue_date' => $this->faker->date(),
            'effective_date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
            'amount_delta' => $this->faker->randomFloat(2, -50000, 50000),
            'new_end_date' => $this->faker->date(),
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/ContractModification.php database/factories/Modules/Contracts/ContractModificationFactory.php tests/Unit/Contracts/Models/ContractModificationTest.php
git commit -m "feat(contracts): add ContractModification model + factory"
```

---

## Task 19 — Model: `ContractSnapshot`

**Files:**
- Create: `app/Modules/Contracts/Models/ContractSnapshot.php`
- Create: `database/factories/Modules/Contracts/ContractSnapshotFactory.php`
- Test: `tests/Unit/Contracts/Models/ContractSnapshotTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractSnapshot;
use Tests\TestCase;

class ContractSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_contract_and_casts_payload(): void
    {
        $s = ContractSnapshot::factory()->create(['payload' => ['foo' => 'bar']]);
        $this->assertInstanceOf(Contract::class, $s->contract);
        $this->assertIsArray($s->payload);
        $this->assertEquals('bar', $s->payload['foo']);
    }

    public function test_unique_per_contract_and_entry_updated_at(): void
    {
        $c = Contract::factory()->create();
        $at = '2026-03-01 10:00:00';
        ContractSnapshot::factory()->for($c)->create(['entry_updated_at' => $at]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ContractSnapshot::factory()->for($c)->create(['entry_updated_at' => $at]);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create model + factory**

```php
<?php
// app/Modules/Contracts/Models/ContractSnapshot.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id','entry_updated_at','status_code','content_hash',
        'payload','source_atom','ingested_at',
    ];

    protected $casts = [
        'entry_updated_at' => 'datetime',
        'ingested_at' => 'datetime',
        'payload' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

```php
<?php
// database/factories/Modules/Contracts/ContractSnapshotFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\Contract;
use Modules\Contracts\Models\ContractSnapshot;

class ContractSnapshotFactory extends Factory
{
    protected $model = ContractSnapshot::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'entry_updated_at' => $this->faker->dateTime(),
            'status_code' => $this->faker->randomElement(['PUB','EV','ADJ','RES','ANUL']),
            'content_hash' => sha1($this->faker->uuid()),
            'payload' => ['sample' => true],
            'source_atom' => 'fake.atom',
            'ingested_at' => now(),
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/ContractSnapshot.php database/factories/Modules/Contracts/ContractSnapshotFactory.php tests/Unit/Contracts/Models/ContractSnapshotTest.php
git commit -m "feat(contracts): add ContractSnapshot model (nivel 3 scaffold)"
```

---

## Task 20 — Models: `ReprocessRun` + `ReprocessAtomRun`

**Files:**
- Create: `app/Modules/Contracts/Models/ReprocessRun.php`
- Create: `app/Modules/Contracts/Models/ReprocessAtomRun.php`
- Factories: both
- Test: `tests/Unit/Contracts/Models/ReprocessRunTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;
use Tests\TestCase;

class ReprocessRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_has_atom_runs(): void
    {
        $run = ReprocessRun::factory()->create();
        ReprocessAtomRun::factory()->for($run)->count(3)->create();
        $this->assertCount(3, $run->atomRuns);
    }

    public function test_casts(): void
    {
        $run = ReprocessRun::factory()->create(['config' => ['from' => '201801']]);
        $this->assertIsArray($run->config);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create models + factories**

```php
<?php
// app/Modules/Contracts/Models/ReprocessRun.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReprocessRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'name','status','started_at','finished_at',
        'total_atoms','processed_atoms','total_entries','failed_entries','config',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'config' => 'array',
    ];

    public function atomRuns(): HasMany
    {
        return $this->hasMany(ReprocessAtomRun::class);
    }
}
```

```php
<?php
// app/Modules/Contracts/Models/ReprocessAtomRun.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReprocessAtomRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'reprocess_run_id','atom_path','atom_hash','status',
        'started_at','finished_at','entries_processed','entries_failed','error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function reprocessRun(): BelongsTo
    {
        return $this->belongsTo(ReprocessRun::class);
    }
}
```

Create factories analogously (use `ReprocessRun::factory()` chained in atomRun).

```php
<?php
// database/factories/Modules/Contracts/ReprocessRunFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ReprocessRun;

class ReprocessRunFactory extends Factory
{
    protected $model = ReprocessRun::class;

    public function definition(): array
    {
        return [
            'name' => 'Test run ' . $this->faker->bothify('##'),
            'status' => 'pending',
            'total_atoms' => 10,
            'config' => ['from' => '201801', 'to' => '201812'],
        ];
    }
}
```

```php
<?php
// database/factories/Modules/Contracts/ReprocessAtomRunFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ReprocessAtomRun;
use Modules\Contracts\Models\ReprocessRun;

class ReprocessAtomRunFactory extends Factory
{
    protected $model = ReprocessAtomRun::class;

    public function definition(): array
    {
        return [
            'reprocess_run_id' => ReprocessRun::factory(),
            'atom_path' => 'storage/app/placsp/201801/extracted/sample.atom',
            'atom_hash' => sha1($this->faker->uuid()),
            'status' => 'pending',
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/ReprocessRun.php app/Modules/Contracts/Models/ReprocessAtomRun.php database/factories/Modules/Contracts/ReprocessRunFactory.php database/factories/Modules/Contracts/ReprocessAtomRunFactory.php tests/Unit/Contracts/Models/ReprocessRunTest.php
git commit -m "feat(contracts): add ReprocessRun + ReprocessAtomRun models"
```

---

## Task 21 — Model: `ParseError`

**Files:**
- Create: `app/Modules/Contracts/Models/ParseError.php`
- Create: `database/factories/Modules/Contracts/ParseErrorFactory.php`
- Test: `tests/Unit/Contracts/Models/ParseErrorTest.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;
use Tests\TestCase;

class ParseErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_atom_run_nullable(): void
    {
        $pe = ParseError::factory()->create();
        $this->assertInstanceOf(ReprocessAtomRun::class, $pe->reprocessAtomRun);

        $pe2 = ParseError::factory()->create(['reprocess_atom_run_id' => null]);
        $this->assertNull($pe2->reprocessAtomRun);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create model + factory**

```php
<?php
// app/Modules/Contracts/Models/ParseError.php
namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParseError extends Model
{
    use HasFactory;

    protected $fillable = [
        'reprocess_atom_run_id','atom_path','entry_external_id',
        'error_code','error_message','raw_fragment',
    ];

    public function reprocessAtomRun(): BelongsTo
    {
        return $this->belongsTo(ReprocessAtomRun::class);
    }
}
```

```php
<?php
// database/factories/Modules/Contracts/ParseErrorFactory.php
namespace Database\Factories\Modules\Contracts;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contracts\Models\ParseError;
use Modules\Contracts\Models\ReprocessAtomRun;

class ParseErrorFactory extends Factory
{
    protected $model = ParseError::class;

    public function definition(): array
    {
        return [
            'reprocess_atom_run_id' => ReprocessAtomRun::factory(),
            'atom_path' => 'fake.atom',
            'entry_external_id' => 'https://fake.example/' . $this->faker->numerify('#######'),
            'error_code' => 'EXTRACTOR_FAILED',
            'error_message' => $this->faker->sentence(),
            'raw_fragment' => '<entry/>',
        ];
    }
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/ParseError.php database/factories/Modules/Contracts/ParseErrorFactory.php tests/Unit/Contracts/Models/ParseErrorTest.php
git commit -m "feat(contracts): add ParseError model"
```

---

## Task 22 — Modify `Organization` model (v2 columns)

**Files:**
- Modify: `app/Modules/Contracts/Models/Organization.php`
- Test: `tests/Unit/Contracts/Models/OrganizationV2Test.php`

- [ ] **Step 1: Write test**

```php
<?php
namespace Tests\Unit\Contracts\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contracts\Models\Organization;
use Tests\TestCase;

class OrganizationV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_columns_fillable(): void
    {
        $o = Organization::factory()->create([
            'buyer_profile_uri' => 'https://example/profile',
            'activity_code' => '1',
            'platform_id' => '31071580150918',
        ]);
        $this->assertEquals('https://example/profile', $o->buyer_profile_uri);
        $this->assertEquals('1', $o->activity_code);
        $this->assertEquals('31071580150918', $o->platform_id);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Update model**

Modify the existing `Organization` model to add the new fillables:

```php
// add to existing $fillable array:
protected $fillable = [
    // ...existing fields...
    'buyer_profile_uri',
    'activity_code',
    'platform_id',
];
```

Update the existing `OrganizationFactory` to optionally set these fields (just make sure they're in the definition with faker defaults).

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Models/Organization.php database/factories/Modules/Contracts/OrganizationFactory.php tests/Unit/Contracts/Models/OrganizationV2Test.php
git commit -m "feat(contracts): add v2 fillable columns to Organization"
```

---

## Task 23 — DTO scaffolding: 11 readonly classes

**Files:**
- Create: `app/Modules/Contracts/Services/Parser/DTOs/EntryDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/TombstoneDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/OrganizationDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/LotDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/ProcessDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/ResultDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/WinningPartyDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/TermsDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/CriterionDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/NoticeDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/DocumentDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/AddressDTO.php`
- Create: `app/Modules/Contracts/Services/Parser/DTOs/ContactDTO.php`
- Test: `tests/Unit/Contracts/Parser/DTOs/DTOInstantiationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit\Contracts\Parser\DTOs;

use Modules\Contracts\Services\Parser\DTOs\EntryDTO;
use Modules\Contracts\Services\Parser\DTOs\OrganizationDTO;
use Tests\TestCase;

class DTOInstantiationTest extends TestCase
{
    public function test_entry_dto_instantiable(): void
    {
        $org = new OrganizationDTO(
            name: 'Ayto. Trujillo',
            dir3: 'L01101954',
            nif: 'P1019900H',
            platform_id: null,
            buyer_profile_uri: null,
            activity_code: '1',
            type_code: '3',
            hierarchy: [],
            address: null,
            contacts: [],
        );

        $entry = new EntryDTO(
            external_id: 'https://x/1',
            link: null,
            expediente: 'TEST/1',
            status_code: 'PUB',
            entry_updated_at: new \DateTimeImmutable('2026-03-20T19:06:57+01:00'),
            organization: $org,
            lots: [],
            process: null,
            results: [],
            terms: null,
            criteria_by_lot: [],
            notices: [],
            documents: [],
        );

        $this->assertEquals('TEST/1', $entry->expediente);
        $this->assertEquals('Ayto. Trujillo', $entry->organization->name);
    }
}
```

- [ ] **Step 2: Run test, expect FAIL**

- [ ] **Step 3: Create DTOs**

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/AddressDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class AddressDTO
{
    public function __construct(
        public ?string $line = null,
        public ?string $postal_code = null,
        public ?string $city_name = null,
        public ?string $country_code = null,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/ContactDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ContactDTO
{
    public function __construct(
        public string $type,  // phone | fax | email | website
        public string $value,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/OrganizationDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class OrganizationDTO
{
    /**
     * @param string[] $hierarchy
     * @param ContactDTO[] $contacts
     */
    public function __construct(
        public string $name,
        public ?string $dir3,
        public ?string $nif,
        public ?string $platform_id,
        public ?string $buyer_profile_uri,
        public ?string $activity_code,
        public ?string $type_code,
        public array $hierarchy,
        public ?AddressDTO $address,
        public array $contacts,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/LotDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class LotDTO
{
    /** @param string[] $cpv_codes */
    public function __construct(
        public int $lot_number,
        public ?string $title,
        public ?string $description,
        public ?string $tipo_contrato_code,
        public ?string $subtipo_contrato_code,
        public array $cpv_codes,
        public ?float $budget_with_tax,
        public ?float $budget_without_tax,
        public ?float $estimated_value,
        public ?float $duration,
        public ?string $duration_unit,
        public ?string $start_date,
        public ?string $end_date,
        public ?string $nuts_code,
        public ?string $lugar_ejecucion,
        public ?string $options_description,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/ProcessDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ProcessDTO
{
    public function __construct(
        public ?string $procedure_code,
        public ?string $urgency_code,
        public ?string $submission_method_code,
        public ?string $contracting_system_code,
        public ?string $fecha_disponibilidad_docs,
        public ?string $fecha_presentacion_limite,
        public ?string $hora_presentacion_limite,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/WinningPartyDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class WinningPartyDTO
{
    public function __construct(
        public string $name,
        public ?string $nif,
        public ?AddressDTO $address,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/ResultDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class ResultDTO
{
    public function __construct(
        public int $lot_number,           // to which lot does this result belong
        public ?WinningPartyDTO $winner,
        public ?float $amount_with_tax,
        public ?float $amount_without_tax,
        public ?float $lower_tender_amount,
        public ?float $higher_tender_amount,
        public ?int $num_offers,
        public ?int $smes_received_tender_quantity,
        public ?bool $sme_awarded,
        public ?string $award_date,
        public ?string $start_date,
        public ?string $formalization_date,
        public ?string $contract_number,
        public ?string $result_code,
        public ?string $description,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/TermsDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class TermsDTO
{
    public function __construct(
        public ?string $language,
        public ?string $funding_program_code,
        public ?string $national_legislation_code,
        public ?bool $over_threshold_indicator,
        public ?int $received_appeal_quantity,
        public ?bool $variant_constraint_indicator,
        public ?bool $required_curricula_indicator,
        public ?string $guarantee_type_code,
        public ?float $guarantee_percentage,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/CriterionDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class CriterionDTO
{
    public function __construct(
        public int $lot_number,
        public string $type_code,   // OBJ | SUBJ
        public ?string $subtype_code,
        public string $description,
        public ?string $note,
        public ?float $weight_numeric,
        public int $sort_order,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/NoticeDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class NoticeDTO
{
    public function __construct(
        public string $notice_type_code,
        public ?string $publication_media,
        public string $issue_date,
        public ?string $document_uri,
        public ?string $document_filename,
        public ?string $document_type_code,
        public ?string $document_type_name,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/DocumentDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class DocumentDTO
{
    public function __construct(
        public string $type,         // legal | technical | additional | general
        public string $name,
        public ?string $uri,
        public ?string $hash,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/TombstoneDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class TombstoneDTO
{
    public function __construct(
        public string $ref,                       // external_id of the annulled contract
        public \DateTimeImmutable $when,
    ) {}
}
```

```php
<?php
// app/Modules/Contracts/Services/Parser/DTOs/EntryDTO.php
namespace Modules\Contracts\Services\Parser\DTOs;

final readonly class EntryDTO
{
    /**
     * @param LotDTO[] $lots
     * @param ResultDTO[] $results
     * @param array<int, CriterionDTO[]> $criteria_by_lot  keyed by lot_number
     * @param NoticeDTO[] $notices
     * @param DocumentDTO[] $documents
     */
    public function __construct(
        public string $external_id,
        public ?string $link,
        public string $expediente,
        public string $status_code,
        public \DateTimeImmutable $entry_updated_at,
        public OrganizationDTO $organization,
        public array $lots,
        public ?ProcessDTO $process,
        public array $results,
        public ?TermsDTO $terms,
        public array $criteria_by_lot,
        public array $notices,
        public array $documents,
    ) {}
}
```

- [ ] **Step 4: Run test, expect PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Contracts/Services/Parser/DTOs tests/Unit/Contracts/Parser/DTOs/DTOInstantiationTest.php
git commit -m "feat(contracts): add 13 readonly DTOs for parser"
```

---

## Task 24 — PHPStan verification + Pint on changes

- [ ] **Step 1: Run PHPStan level 8 on module**

```bash
./vendor/bin/phpstan analyse app/Modules/Contracts --level=8
```
Expected: `[OK] No errors`.

- [ ] **Step 2: Run Laravel Pint**

```bash
./vendor/bin/pint app/Modules/Contracts tests/Unit/Contracts tests/Feature/Contracts database/migrations/2026_04_25_* database/factories/Modules/Contracts
```

- [ ] **Step 3: Commit any diffs**

```bash
git add -A
git diff --cached --quiet || git commit -m "style(contracts): pint formatting for v2 module files"
```

---

## Task 25 — Phase 1.0 gate verification (checkpoint + push)

- [ ] **Step 1: Run full test suite for the module**

```bash
php artisan test tests/Unit/Contracts tests/Feature/Contracts/Migrations
```
Expected: all green.

- [ ] **Step 2: Verify `migrate:fresh` clean**

```bash
php artisan migrate:fresh --seed
```
Expected: no errors.

- [ ] **Step 3: Verify factories all instantiable (ad-hoc smoke test)**

```bash
php artisan tinker --execute='
foreach ([
    \Modules\Contracts\Models\Contract::class,
    \Modules\Contracts\Models\ContractLot::class,
    \Modules\Contracts\Models\Award::class,
    \Modules\Contracts\Models\AwardingCriterion::class,
    \Modules\Contracts\Models\ContractNotice::class,
    \Modules\Contracts\Models\ContractDocument::class,
    \Modules\Contracts\Models\ContractModification::class,
    \Modules\Contracts\Models\ContractSnapshot::class,
    \Modules\Contracts\Models\Organization::class,
    \Modules\Contracts\Models\Company::class,
    \Modules\Contracts\Models\ReprocessRun::class,
    \Modules\Contracts\Models\ReprocessAtomRun::class,
    \Modules\Contracts\Models\ParseError::class,
] as $m) {
    $inst = $m::factory()->create();
    echo "$m OK id={$inst->id}\n";
}'
```
Expected: 13 lines `... OK id=N`.

- [ ] **Step 4: Push branch and open PR**

```bash
git push -u origin feature/contracts-v2-foundation
gh pr create --title "contracts v2 — Phase 1.0 foundation" --body "$(cat <<'EOF'
## Summary
- 12 migraciones v2 (lots, criteria, modifications, snapshots, reprocess_runs, atom_runs, parse_errors, contracts v2 cols, awards FK, orgs v2 cols, fulltext index, legacy cleanup).
- 13 modelos Eloquent con relations + casts + scopes + factories.
- 13 DTOs readonly PHP 8.4 para el parser.
- Cobertura de tests unit/feature para cada capa.

## Test plan
- [x] `php artisan migrate:fresh` clean
- [x] `php artisan test tests/Unit/Contracts tests/Feature/Contracts/Migrations` verde
- [x] PHPStan L8 sobre `app/Modules/Contracts` verde
- [x] Pint sin diffs
- [x] Smoke test: las 13 factories instanciables

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 5: Merge PR to main once approved, continue with Phase 1.1**

```bash
gh pr merge --squash --delete-branch
git checkout main && git pull
```
