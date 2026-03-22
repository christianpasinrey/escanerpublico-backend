# Modular Architecture Refactoring Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor escanerpublico-backend from monolithic to modular architecture following the ilicitaciones pattern. One `Contracts` module containing all domain models (Organization, Company, Contract, Award, ContractNotice, ContractDocument). Normalize the DB: organizations and companies as first-class tables, awards as explicit pivot. Global polymorphic `Address` (using `nnjeim/world` for normalized countries/states/cities) and `Contact` models shared across modules. Empty `Budgets` and `Legislation` module shells for future development.

**Architecture:** Modules organized by **business domain** (Contracts, Budgets, Legislation), auto-discovered by `ModuleServiceProvider`. Each module owns its models, routes, controllers, services, jobs, and commands. The `organizations` and `companies` tables are shared infrastructure — each module that needs them creates its own Model class pointing to the same table. Addresses and contacts are polymorphic global models (`app/Models/Address`, `app/Models/Contact`) that any entity can use via `morphMany`. The 2-pass batch import (from ilicitaciones) bulk-creates orgs/companies first, then upserts contracts with resolved FKs.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL, `nnjeim/world` (normalized geo data), Nuxt 3 (Vue 3 + Tailwind v4)

---

## Target Structure

```
app/
├── Models/                              ← Global polymorphic models
│   ├── Address.php                      → table: addresses (morphTo addressable)
│   └── Contact.php                      → table: contacts (morphTo contactable)
│
├── Modules/
│   ├── Contracts/
│   │   ├── ContractsServiceProvider.php
│   │   ├── Models/
│   │   │   ├── Contract.php
│   │   │   ├── Organization.php         → table: organizations
│   │   │   ├── Company.php              → table: companies
│   │   │   ├── Award.php                → table: awards (pivot contract↔company)
│   │   │   ├── ContractNotice.php       → table: contract_notices
│   │   │   └── ContractDocument.php     → table: contract_documents
│   │   ├── Services/
│   │   │   └── PlacspParser.php
│   │   ├── Jobs/
│   │   │   └── ProcessPlacspFile.php
│   │   ├── Console/
│   │   │   └── SyncContracts.php
│   │   ├── Http/Controllers/
│   │   │   ├── ContractController.php
│   │   │   ├── OrganizationController.php
│   │   │   └── CompanyController.php
│   │   └── Routes/
│   │       └── api.php
│   │
│   ├── Budgets/                          (shell — future)
│   │   └── BudgetsServiceProvider.php
│   │
│   └── Legislation/                      (shell — future)
│       └── LegislationServiceProvider.php

nnjeim/world provides:
    world_countries, world_divisions (states), world_cities ← seeded geo data
```

## Database Schema (Target)

```
organizations              companies              contracts
─────────────              ─────────              ─────────
id                         id                     id
name (indexed)             name (indexed)         external_id (unique)
identifier (DIR3,idx)      identifier (indexed)   expediente
nif (indexed)              nif (indexed)          organization_id → FK
type_code                  timestamps             status_code
hierarchy (JSON)                                  objeto
parent_name                awards                 tipo_contrato_code
timestamps                 ──────                 procedimiento_code
  ↕ morphMany addresses   id                     urgencia_code
  ↕ morphMany contacts    contract_id → FK       submission_method_code
                           company_id → FK        contracting_system_code
                           amount                 importe_sin_iva
addresses (polymorphic)    amount_without_tax     importe_con_iva
─────────────────────      procedure_type         valor_estimado
id                         urgency                cpv_codes (JSON)
addressable_type/id        award_date             comunidad_autonoma
line (street)              start_date             nuts_code
postal_code                formalization_date     lugar_ejecucion
city_id → world_cities     contract_number        duracion / duracion_unidad
state_id → world_divisions sme_awarded            fecha_presentacion_limite
country_id → world_countries num_offers           hora_presentacion_limite
timestamps                 result_code            fecha_disponibilidad_docs
                           timestamps             fecha_inicio / fecha_fin
contacts (polymorphic)                            criterios_adjudicacion (JSON)
────────────────────       contract_notices       garantia_tipo_code
id                         ────────────────       garantia_porcentaje
contactable_type/id        (unchanged)            idioma
type (phone/email/                                opciones_descripcion
      fax/website)         contract_documents     link
value                      ──────────────────     synced_at
timestamps                 (unchanged)            timestamps

nnjeim/world (seeded):
  world_countries, world_divisions (states), world_cities
```

## File Structure

### Infrastructure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `app/Providers/ModuleServiceProvider.php` | Auto-discover modules in `app/Modules/` |
| Modify | `bootstrap/providers.php` | Register ModuleServiceProvider |
| Modify | `composer.json` | Add `Modules\\` PSR-4 namespace + `nnjeim/world` |

### Global Polymorphic Models

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `app/Models/Address.php` | Polymorphic address with world FKs |
| Create | `app/Models/Contact.php` | Polymorphic contact (phone/email/fax/website) |

### Contracts Module (all files)

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `app/Modules/Contracts/ContractsServiceProvider.php` | Register routes, commands |
| Create | `app/Modules/Contracts/Models/Organization.php` | Org model → `organizations` table |
| Create | `app/Modules/Contracts/Models/Company.php` | Company model → `companies` table |
| Create | `app/Modules/Contracts/Models/Contract.php` | Contract model (slim, FK to org) |
| Create | `app/Modules/Contracts/Models/Award.php` | Explicit pivot contract↔company |
| Create | `app/Modules/Contracts/Models/ContractNotice.php` | Notice model (moved) |
| Create | `app/Modules/Contracts/Models/ContractDocument.php` | Document model (moved) |
| Create | `app/Modules/Contracts/Services/PlacspParser.php` | XML parser (adapted output) |
| Create | `app/Modules/Contracts/Jobs/ProcessPlacspFile.php` | 2-pass batch processing |
| Create | `app/Modules/Contracts/Console/SyncContracts.php` | Download + dispatch command |
| Create | `app/Modules/Contracts/Http/Controllers/ContractController.php` | Contract API |
| Create | `app/Modules/Contracts/Http/Controllers/OrganizationController.php` | Org API |
| Create | `app/Modules/Contracts/Http/Controllers/CompanyController.php` | Company API |
| Create | `app/Modules/Contracts/Routes/api.php` | All contract-domain routes |

### Future Module Shells

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `app/Modules/Budgets/BudgetsServiceProvider.php` | Empty shell |
| Create | `app/Modules/Legislation/LegislationServiceProvider.php` | Empty shell |

### Migrations

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_03_22_200000_create_addresses_table.php` | Polymorphic addresses with world FKs |
| Create | `database/migrations/2026_03_22_200001_create_contacts_table.php` | Polymorphic contacts |
| Create | `database/migrations/2026_03_22_200002_create_organizations_table.php` | Slim orgs (no address/contact fields) |
| Create | `database/migrations/2026_03_22_200003_create_companies_table.php` | Slim companies |
| Create | `database/migrations/2026_03_22_200004_create_awards_table.php` | Pivot contract↔company |
| Create | `database/migrations/2026_03_22_200005_refactor_contracts_table.php` | Remove org/adj fields, add FK, backfill |

### Cleanup

| Action | File | Responsibility |
|--------|------|----------------|
| Delete | `app/Models/Contract.php` | Replaced by Modules version |
| Delete | `app/Models/ContractNotice.php` | Replaced |
| Delete | `app/Models/ContractDocument.php` | Replaced |
| Delete | `app/Services/PlacspParser.php` | Replaced |
| Delete | `app/Jobs/ProcessPlacspFile.php` | Replaced |
| Delete | `app/Console/Commands/SyncContracts.php` | Replaced |
| Delete | `app/Http/Controllers/ContractController.php` | Replaced |
| Delete | `app/Contracts/ScraperInterface.php` | Dead code |
| Modify | `routes/api.php` | Remove contract routes (now in module) |

### Frontend

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `escanerpublico-frontend/pages/contratos/[id].vue` | Adapt to nested response |
| Modify | `escanerpublico-frontend/pages/contratos.vue` | Adapt to new contract shape |

---

## Task 1: Module infrastructure + nnjeim/world + directory scaffold

**Files:**
- Create: `app/Providers/ModuleServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: `composer.json`
- Create: directory tree for all 3 modules

- [ ] **Step 0: Install nnjeim/world**

```bash
cd /c/laragon/www/escanerpublico-backend
composer require nnjeim/world
php artisan world:install
```

This publishes config, runs migrations (world_countries, world_divisions, world_cities, etc.), and seeds geo data.

- [ ] **Step 1: Create ModuleServiceProvider**

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (File::directories($modulesPath) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $providerClass = "Modules\\{$moduleName}\\{$moduleName}ServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    public function boot(): void {}
}
```

- [ ] **Step 2: Register in bootstrap/providers.php**

Add `App\Providers\ModuleServiceProvider::class` to the array.

- [ ] **Step 3: Add Modules namespace to composer.json autoload.psr-4**

```json
"Modules\\": "app/Modules/"
```

- [ ] **Step 4: Create directory structure**

```bash
mkdir -p app/Modules/Contracts/{Models,Services,Jobs,Console,Http/Controllers,Routes}
mkdir -p app/Modules/Budgets
mkdir -p app/Modules/Legislation
```

- [ ] **Step 5: Create empty module shells**

`app/Modules/Budgets/BudgetsServiceProvider.php`:
```php
<?php

namespace Modules\Budgets;

use Illuminate\Support\ServiceProvider;

class BudgetsServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
```

`app/Modules/Legislation/LegislationServiceProvider.php`:
```php
<?php

namespace Modules\Legislation;

use Illuminate\Support\ServiceProvider;

class LegislationServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
```

- [ ] **Step 6: composer dump-autoload and verify**

```bash
cd /c/laragon/www/escanerpublico-backend && composer dump-autoload
php artisan about
```

- [ ] **Step 7: Commit**

```bash
git add app/Providers/ModuleServiceProvider.php bootstrap/providers.php composer.json app/Modules/
git commit -m "feat: add module infrastructure with Contracts, Budgets, Legislation shells"
```

---

## Task 2: Migrations — addresses, contacts, organizations, companies, awards, refactor contracts

**Files:**
- Create: 6 migration files

- [ ] **Step 1: Create addresses migration (polymorphic, with nnjeim/world FKs)**

File: `database/migrations/2026_03_22_200000_create_addresses_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable'); // addressable_type, addressable_id (indexed)
            $table->string('line', 500)->nullable()->comment('Street address');
            $table->string('postal_code', 20)->nullable();
            $table->foreignId('city_id')->nullable()->constrained('world_cities')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('world_divisions')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('world_countries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
```

- [ ] **Step 2: Create contacts migration (polymorphic)**

File: `database/migrations/2026_03_22_200001_create_contacts_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->morphs('contactable'); // contactable_type, contactable_id (indexed)
            $table->string('type', 20)->comment('phone, email, fax, website');
            $table->string('value', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
```

- [ ] **Step 3: Create organizations migration (slim — no address/contact fields)**

File: `database/migrations/2026_03_22_200002_create_organizations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('identifier')->nullable()->index()->comment('DIR3 code');
            $table->string('nif', 20)->nullable()->index();
            $table->string('type_code', 10)->nullable();
            $table->json('hierarchy')->nullable();
            $table->string('parent_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
```

- [ ] **Step 4: Create companies migration (slim)**

File: `database/migrations/2026_03_22_200003_create_companies_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('identifier')->nullable()->index();
            $table->string('nif', 50)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

- [ ] **Step 5: Create awards migration**

File: `database/migrations/2026_03_22_200004_create_awards_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('amount_without_tax', 15, 2)->nullable();
            $table->string('procedure_type', 10)->nullable();
            $table->string('urgency', 10)->nullable();
            $table->date('award_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('formalization_date')->nullable();
            $table->string('contract_number')->nullable();
            $table->boolean('sme_awarded')->nullable();
            $table->unsignedInteger('num_offers')->nullable();
            $table->string('result_code', 10)->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
```

- [ ] **Step 6: Create contracts refactoring migration**

File: `database/migrations/2026_03_22_200005_refactor_contracts_table.php`

This migration:
1. Adds `organization_id` FK to contracts
2. Backfills organizations from existing `organo_*` fields
3. Backfills companies + awards from `adjudicatario_*` fields
4. Drops all redundant columns

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add organization_id FK
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
        });

        // 2. Backfill
        $this->backfillOrganizations();
        $this->backfillCompaniesAndAwards();

        // 3. Drop redundant columns
        Schema::table('contracts', function (Blueprint $table) {
            $columns = [
                'organo_contratante', 'organo_dir3', 'organo_superior',
                'organo_nif', 'organo_website', 'organo_telefono', 'organo_email',
                'organo_direccion', 'organo_ciudad', 'organo_cp', 'organo_tipo_code',
                'organo_jerarquia',
                'adjudicatario_nombre', 'adjudicatario_nif',
                'importe_adjudicacion_sin_iva', 'importe_adjudicacion_con_iva',
                'fecha_adjudicacion', 'fecha_formalizacion',
                'resultado_code', 'num_ofertas', 'sme_awarded', 'contrato_numero',
            ];

            // Only drop columns that actually exist (some may not if migrations ran partially)
            $existing = Schema::getColumnListing('contracts');
            $toDrop = array_intersect($columns, $existing);
            if ($toDrop) {
                $table->dropColumn($toDrop);
            }
        });
    }

    private function backfillOrganizations(): void
    {
        // Insert unique orgs from contracts
        $seen = [];
        DB::table('contracts')
            ->whereNotNull('organo_contratante')
            ->where('organo_contratante', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($contracts) use (&$seen) {
                foreach ($contracts as $c) {
                    $key = $c->organo_dir3 ?: md5($c->organo_contratante);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $orgId = DB::table('organizations')->insertGetId([
                        'name' => $c->organo_contratante,
                        'identifier' => $c->organo_dir3,
                        'nif' => $c->organo_nif ?? null,
                        'type_code' => $c->organo_tipo_code ?? null,
                        'hierarchy' => $c->organo_jerarquia ?? null,
                        'parent_name' => $c->organo_superior ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create address if we have any address data
                    if ($c->organo_direccion || $c->organo_ciudad || $c->organo_cp) {
                        DB::table('addresses')->insert([
                            'addressable_type' => 'Modules\\Contracts\\Models\\Organization',
                            'addressable_id' => $orgId,
                            'line' => $c->organo_direccion ?? null,
                            'postal_code' => $c->organo_cp ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Create contacts
                    $contacts = [];
                    if (!empty($c->organo_telefono)) $contacts[] = ['type' => 'phone', 'value' => $c->organo_telefono];
                    if (!empty($c->organo_email)) $contacts[] = ['type' => 'email', 'value' => $c->organo_email];
                    if (!empty($c->organo_website)) $contacts[] = ['type' => 'website', 'value' => $c->organo_website];
                    foreach ($contacts as $contact) {
                        DB::table('contacts')->insert(array_merge($contact, [
                            'contactable_type' => 'Modules\\Contracts\\Models\\Organization',
                            'contactable_id' => $orgId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                    }
                }
            });

        // Set organization_id FK — match by DIR3 first, then by name
        DB::statement("
            UPDATE contracts c
            JOIN organizations o ON (
                (c.organo_dir3 IS NOT NULL AND c.organo_dir3 != '' AND o.identifier = c.organo_dir3)
                OR (o.name = c.organo_contratante AND (c.organo_dir3 IS NULL OR c.organo_dir3 = ''))
            )
            SET c.organization_id = o.id
            WHERE c.organization_id IS NULL
        ");
    }

    private function backfillCompaniesAndAwards(): void
    {
        // Insert unique companies
        $seen = [];
        DB::table('contracts')
            ->whereNotNull('adjudicatario_nombre')
            ->where('adjudicatario_nombre', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($contracts) use (&$seen) {
                foreach ($contracts as $c) {
                    $key = $c->adjudicatario_nif ?: md5($c->adjudicatario_nombre);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    DB::table('companies')->insertOrIgnore([
                        'name' => $c->adjudicatario_nombre,
                        'identifier' => $c->adjudicatario_nif,
                        'nif' => $c->adjudicatario_nif,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Create awards — resolve company_id by NIF or name
        DB::table('contracts')
            ->whereNotNull('adjudicatario_nombre')
            ->where('adjudicatario_nombre', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($contracts) {
                foreach ($contracts as $c) {
                    $companyId = null;
                    if ($c->adjudicatario_nif) {
                        $companyId = DB::table('companies')->where('nif', $c->adjudicatario_nif)->value('id');
                    }
                    if (!$companyId) {
                        $companyId = DB::table('companies')->where('name', $c->adjudicatario_nombre)->value('id');
                    }
                    if (!$companyId) continue;

                    DB::table('awards')->insertOrIgnore([
                        'contract_id' => $c->id,
                        'company_id' => $companyId,
                        'amount' => $c->importe_adjudicacion_con_iva,
                        'amount_without_tax' => $c->importe_adjudicacion_sin_iva,
                        'procedure_type' => $c->procedimiento_code,
                        'urgency' => $c->urgencia_code,
                        'award_date' => $c->fecha_adjudicacion,
                        'start_date' => $c->fecha_inicio,
                        'formalization_date' => $c->fecha_formalizacion,
                        'contract_number' => $c->contrato_numero ?? null,
                        'sme_awarded' => $c->sme_awarded ?? null,
                        'num_offers' => $c->num_ofertas,
                        'result_code' => $c->resultado_code,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 6: Verify backfill**

```bash
php artisan tinker --execute="
echo 'Organizations: ' . DB::table('organizations')->count() . PHP_EOL;
echo 'Companies: ' . DB::table('companies')->count() . PHP_EOL;
echo 'Awards: ' . DB::table('awards')->count() . PHP_EOL;
echo 'Contracts with org: ' . DB::table('contracts')->whereNotNull('organization_id')->count() . PHP_EOL;
"
```

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_03_22_20000*
git commit -m "feat: create addresses, contacts, organizations, companies, awards; backfill + slim contracts"
```

---

## Task 3: Global models + Contracts module models

**Files:** Create 2 global models + 6 module models

- [ ] **Step 0: Create global Address model**

File: `app/Models/Address.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;

class Address extends Model
{
    protected $guarded = ['id'];

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function city(): BelongsTo { return $this->belongsTo(City::class); }
    public function country(): BelongsTo { return $this->belongsTo(Country::class); }
}
```

- [ ] **Step 0b: Create global Contact model**

File: `app/Models/Contact.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 1: Create Organization model**

```php
<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Organization extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hierarchy' => 'array'];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }
}
```

- [ ] **Step 2: Create Company model**

```php
<?php

namespace Modules\Contracts\Models;

use App\Models\Address;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model
{
    protected $guarded = ['id'];

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'awards')
            ->withPivot('amount', 'amount_without_tax', 'award_date', 'start_date',
                'formalization_date', 'contract_number', 'sme_awarded', 'num_offers', 'result_code')
            ->withTimestamps();
    }

    public function awards(): HasMany { return $this->hasMany(Award::class); }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }
    public function contacts(): MorphMany { return $this->morphMany(Contact::class, 'contactable'); }
}
```

- [ ] **Step 3: Create Contract model**

```php
<?php

namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cpv_codes' => 'array',
            'criterios_adjudicacion' => 'array',
            'importe_sin_iva' => 'decimal:2',
            'importe_con_iva' => 'decimal:2',
            'valor_estimado' => 'decimal:2',
            'duracion' => 'decimal:2',
            'garantia_porcentaje' => 'decimal:2',
            'fecha_presentacion_limite' => 'date',
            'fecha_disponibilidad_docs' => 'date',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public const STATUS_LABELS = [
        'PRE' => 'Anuncio previo', 'PUB' => 'En plazo',
        'EV' => 'Pendiente de adjudicación', 'ADJ' => 'Adjudicada',
        'RES' => 'Resuelta', 'ANUL' => 'Anulada',
    ];

    public const TIPO_LABELS = [
        '1' => 'Obras', '2' => 'Servicios', '3' => 'Suministros',
        '7' => 'Gestión de servicios públicos', '8' => 'Concesión de obras',
        '21' => 'Concesión de servicios', '31' => 'Colaboración público-privada',
        '40' => 'Administrativo especial', '50' => 'Privado',
    ];

    public const PROCEDIMIENTO_LABELS = [
        '1' => 'Abierto', '2' => 'Restringido',
        '3' => 'Negociado sin publicidad', '4' => 'Negociado con publicidad',
        '5' => 'Diálogo competitivo', '6' => 'Abierto simplificado',
        '100' => 'Basado en acuerdo marco', '999' => 'Otros',
    ];

    public function scopeStatus($query, string $s) { return $query->where('status_code', $s); }
    public function scopeTipo($query, string $t) { return $query->where('tipo_contrato_code', $t); }
    public function scopeProcedimiento($query, string $p) { return $query->where('procedimiento_code', $p); }
    public function scopeImporteMin($query, float $v) { return $query->where('importe_con_iva', '>=', $v); }
    public function scopeImporteMax($query, float $v) { return $query->where('importe_con_iva', '<=', $v); }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'awards')
            ->withPivot('amount', 'amount_without_tax', 'award_date', 'start_date',
                'formalization_date', 'contract_number', 'sme_awarded', 'num_offers', 'result_code')
            ->withTimestamps();
    }
    public function awards(): HasMany { return $this->hasMany(Award::class); }
    public function notices(): HasMany { return $this->hasMany(ContractNotice::class)->orderBy('issue_date'); }
    public function documents(): HasMany { return $this->hasMany(ContractDocument::class); }
}
```

- [ ] **Step 4: Create Award model**

```php
<?php

namespace Modules\Contracts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'amount_without_tax' => 'decimal:2',
            'sme_awarded' => 'boolean',
            'award_date' => 'date', 'start_date' => 'date', 'formalization_date' => 'date',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
```

- [ ] **Step 5: Create ContractNotice + ContractDocument (namespace change only)**

Same as current models but namespace `Modules\Contracts\Models` instead of `App\Models`.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Address.php app/Models/Contact.php app/Modules/Contracts/Models/
git commit -m "feat: add global Address/Contact polymorphic models + all Contracts module models"
```

---

## Task 4: Contracts module — PlacspParser adapted

**Files:**
- Create: `app/Modules/Contracts/Services/PlacspParser.php`

- [ ] **Step 1: Copy and adapt the parser**

The implementer must:
1. Copy `app/Services/PlacspParser.php` → `app/Modules/Contracts/Services/PlacspParser.php`
2. Change namespace to `Modules\Contracts\Services`
3. Restructure output so org data goes into `$data['_organization']` sub-array and award data into `$data['_award']` sub-array — instead of flat `organo_*` / `adjudicatario_*` fields

The org extraction section should output:
```php
$data['_organization'] = array_filter([
    'name' => /* organo_contratante value */,
    'identifier' => /* DIR3 value */,
    'nif' => /* NIF value */,
    'type_code' => /* ContractingPartyTypeCode */,
    'hierarchy' => /* hierarchy array */,
    'parent_name' => /* first parent */,
    // Address + contacts go as sub-arrays for ProcessPlacspFile to persist polymorphically
    '_address' => array_filter([
        'line' => /* AddressLine */,
        'postal_code' => /* PostalZone */,
        'city_name' => /* CityName (resolved to city_id in job) */,
        'country_code' => /* CountryIdentificationCode, e.g. "ES" */,
    ], fn($v) => $v !== null && $v !== ''),
    '_contacts' => array_filter([
        ['type' => 'phone', 'value' => /* Telephone */],
        ['type' => 'fax', 'value' => /* Telefax */],
        ['type' => 'email', 'value' => /* ElectronicMail */],
        ['type' => 'website', 'value' => /* WebsiteURI */],
    ], fn($c) => !empty($c['value'])),
], fn($v) => $v !== null && $v !== '' && $v !== []);
```

The TenderResult section should output:
```php
$data['_award'] = array_filter([
    'company_name' => /* adjudicatario nombre */,
    'company_nif' => /* adjudicatario NIF */,
    'amount' => /* importe adjudicación con IVA */,
    'amount_without_tax' => /* importe adjudicación sin IVA */,
    'award_date' => /* fecha adjudicación */,
    'formalization_date' => /* fecha formalización */,
    'start_date' => /* fecha inicio from TenderResult */,
    'contract_number' => /* Contract/ID */,
    'sme_awarded' => /* SMEAwardedIndicator */,
    'num_offers' => /* ReceivedTenderQuantity */,
    'result_code' => /* ResultCode */,
], fn($v) => $v !== null && $v !== '');
```

Contract-level fields stay flat in `$data`: expediente, objeto, external_id, link, status_code, tipo_contrato_code, procedimiento_code, urgencia_code, submission_method_code, contracting_system_code, importe_sin_iva, importe_con_iva, valor_estimado, cpv_codes, location fields, duration, tendering terms (criterios, garantia, idioma, opciones).

`_notices` and `_documents` arrays stay as they are.

- [ ] **Step 2: Verify with tinker**

```bash
php artisan tinker --execute="
\$parser = new \Modules\Contracts\Services\PlacspParser();
\$xml = file_get_contents('storage/app/placsp/201801/extracted/licitacionesPerfilesContratanteCompleto3_20200522_234632.atom');
\$c = \$parser->parseAtomFile(\$xml)[0];
echo 'Org: ' . json_encode(\$c['_organization'] ?? 'MISSING') . PHP_EOL;
echo 'Award: ' . json_encode(\$c['_award'] ?? 'MISSING') . PHP_EOL;
echo 'Notices: ' . count(\$c['_notices'] ?? []) . PHP_EOL;
"
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Contracts/Services/PlacspParser.php
git commit -m "feat: move PlacspParser to Contracts module, separate org/award output"
```

---

## Task 5: Contracts module — ProcessPlacspFile (2-pass batch)

**Files:**
- Create: `app/Modules/Contracts/Jobs/ProcessPlacspFile.php`

- [ ] **Step 1: Create the job**

The implementer MUST read the reference implementation at `/c/laragon/www/ilicitaciones/app/Modules/Contratos/Jobs/ProcessPlacspFile.php` and adapt it for our schema. Key adaptations:

- Use `Organization` instead of `Organismo`, `Company` instead of `Empresa`, `Award` instead of `Adjudicacion`, `Contract` instead of `Licitacion`
- Organization is slim (no address/contact fields) — after `insertOrIgnore`, persist `_address` to `addresses` table and `_contacts` to `contacts` table as polymorphic records
- For `_address.city_name`, resolve `city_id` via `Nnjeim\World\Models\City::where('name', $cityName)->value('id')` (null if not found)
- For `_address.country_code`, resolve `country_id` via `Nnjeim\World\Models\Country::where('iso2', $code)->value('id')`
- After the `Contract::upsert()`, loop through entries to sync notices and documents (delete+insert per contract) — this is new vs ilicitaciones
- Cache keys: `dir3:{code}` for orgs, `nif:{nif}` for companies

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Contracts/Jobs/ProcessPlacspFile.php
git commit -m "feat: add 2-pass batch ProcessPlacspFile with entity caching"
```

---

## Task 6: Contracts module — Command, controllers, routes, service provider

**Files:**
- Create: `app/Modules/Contracts/Console/SyncContracts.php`
- Create: `app/Modules/Contracts/Http/Controllers/ContractController.php`
- Create: `app/Modules/Contracts/Http/Controllers/OrganizationController.php`
- Create: `app/Modules/Contracts/Http/Controllers/CompanyController.php`
- Create: `app/Modules/Contracts/Routes/api.php`
- Create: `app/Modules/Contracts/ContractsServiceProvider.php`

- [ ] **Step 1: Move SyncContracts command**

Copy from `app/Console/Commands/SyncContracts.php`, change namespace to `Modules\Contracts\Console`, update job import to `Modules\Contracts\Jobs\ProcessPlacspFile`.

- [ ] **Step 2: Create ContractController**

Adapt from existing. Key changes:
- Namespace `Modules\Contracts\Http\Controllers`
- `index()`: add `->with('organization:id,name')` to eager-load org name in list
- `show()`: `$contract->load(['organization', 'awards.company', 'notices', 'documents'])`
- `buildTimeline()`: read award dates from `$contract->awards->first()` instead of contract fields

- [ ] **Step 3: Create OrganizationController**

```php
<?php

namespace Modules\Contracts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contracts\Models\Organization;

class OrganizationController
{
    public function index(Request $request): JsonResponse
    {
        $query = Organization::query();

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('nif', 'like', "%{$q}%")
                  ->orWhere('identifier', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->withCount('contracts')
                ->orderByDesc('contracts_count')
                ->paginate($request->input('per_page', 25))
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->loadCount('contracts');
        return response()->json($organization);
    }
}
```

- [ ] **Step 4: Create CompanyController** (same pattern)

- [ ] **Step 5: Create routes**

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\OrganizationController;
use Modules\Contracts\Http\Controllers\CompanyController;

Route::prefix('api/v1')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/stats', [ContractController::class, 'stats']);
    Route::get('/contracts/filters', [ContractController::class, 'filters']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{company}', [CompanyController::class, 'show']);
});
```

- [ ] **Step 6: Create ContractsServiceProvider**

```php
<?php

namespace Modules\Contracts;

use Illuminate\Support\ServiceProvider;

class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Contracts\Console\SyncContracts::class,
            ]);
        }
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Contracts/{Console,Http,Routes,ContractsServiceProvider.php}
git commit -m "feat: add Contracts module command, controllers, routes, service provider"
```

---

## Task 7: Cleanup old monolithic code

- [ ] **Step 1: Delete old files**

```bash
rm app/Models/Contract.php app/Models/ContractNotice.php app/Models/ContractDocument.php
rm app/Services/PlacspParser.php
rm app/Jobs/ProcessPlacspFile.php
rm app/Console/Commands/SyncContracts.php
rm app/Http/Controllers/ContractController.php
rm -f app/Contracts/ScraperInterface.php
```

- [ ] **Step 2: Clean routes/api.php** — keep only health check

- [ ] **Step 3: Verify**

```bash
php artisan route:list --columns=method,uri
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor: remove monolithic code, all logic now in Contracts module"
```

---

## Task 8: Re-sync + verify

- [ ] **Step 1: Re-sync all data**

```bash
php artisan contracts:sync --all --sync
```

- [ ] **Step 2: Verify counts**

- [ ] **Step 3: Test API endpoints**

---

## Task 9: Frontend — Adapt to modular API

- [ ] **Step 1: Update `pages/contratos/[id].vue`**

The `contract` object now has:
- `contract.organization` (object) instead of `contract.organo_*`
- `contract.awards` (array) instead of `contract.adjudicatario_*`
- Award data: `contract.awards[0].company.name`, `contract.awards[0].amount`, etc.

- [ ] **Step 2: Update `pages/contratos.vue`**

The list endpoint now returns contracts with `organization` eager-loaded. Show `contract.organization?.name` instead of `contract.organo_contratante`.

- [ ] **Step 3: Commit**

---

## Summary

| Task | What |
|------|------|
| 1 | Module infrastructure + `nnjeim/world` + scaffold (Contracts, Budgets, Legislation) |
| 2 | Migrations: addresses, contacts, organizations, companies, awards + backfill + slim contracts |
| 3 | Global Address/Contact polymorphic models + all 6 Contracts module models |
| 4 | PlacspParser adapted (separate _organization/_award output) |
| 5 | ProcessPlacspFile 2-pass batch with caching (ilicitaciones pattern) |
| 6 | Command, controllers (contract+org+company), routes, service provider |
| 7 | Delete old monolithic code |
| 8 | Re-sync + verify |
| 9 | Frontend adaptation |
