<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add organization_id FK (idempotent)
        if (! Schema::hasColumn('contracts', 'organization_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()
                    ->constrained('organizations')->nullOnDelete();
            });
        }

        // 2. Backfill (skip if already done)
        if (DB::table('organizations')->count() === 0) {
            $this->backfillOrganizations();
        }
        if (DB::table('companies')->count() === 0) {
            $this->backfillCompaniesAndAwards();
        }

        // 3. Drop redundant columns (only those that exist)
        $allCols = Schema::getColumnListing('contracts');
        $toDrop = array_intersect($allCols, [
            'organo_contratante', 'organo_dir3', 'organo_superior',
            'organo_nif', 'organo_website', 'organo_telefono', 'organo_email',
            'organo_direccion', 'organo_ciudad', 'organo_cp', 'organo_tipo_code',
            'organo_jerarquia',
            'adjudicatario_nombre', 'adjudicatario_nif',
            'importe_adjudicacion_sin_iva', 'importe_adjudicacion_con_iva',
            'fecha_adjudicacion', 'fecha_formalizacion',
            'resultado_code', 'num_ofertas', 'sme_awarded', 'contrato_numero',
        ]);

        if (! empty($toDrop)) {
            Schema::table('contracts', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn(array_values($toDrop));
            });
        }
    }

    private function backfillOrganizations(): void
    {
        $seen = [];
        DB::table('contracts')
            ->whereNotNull('organo_contratante')
            ->where('organo_contratante', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($contracts) use (&$seen) {
                foreach ($contracts as $c) {
                    $key = $c->organo_dir3 ?: md5($c->organo_contratante);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $orgId = DB::table('organizations')->insertGetId([
                        'name' => $c->organo_contratante,
                        'identifier' => $c->organo_dir3 ?? null,
                        'nif' => $c->organo_nif ?? null,
                        'type_code' => $c->organo_tipo_code ?? null,
                        'hierarchy' => $c->organo_jerarquia ?? null,
                        'parent_name' => $c->organo_superior ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create polymorphic address
                    $direccion = $c->organo_direccion ?? null;
                    $ciudad = $c->organo_ciudad ?? null;
                    $cp = $c->organo_cp ?? null;
                    if ($direccion || $ciudad || $cp) {
                        DB::table('addresses')->insert([
                            'addressable_type' => 'Modules\\Contracts\\Models\\Organization',
                            'addressable_id' => $orgId,
                            'line' => $direccion,
                            'postal_code' => $cp,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Create polymorphic contacts
                    $contactData = [];
                    if (! empty($c->organo_telefono)) {
                        $contactData[] = ['type' => 'phone', 'value' => $c->organo_telefono];
                    }
                    if (! empty($c->organo_email)) {
                        $contactData[] = ['type' => 'email', 'value' => $c->organo_email];
                    }
                    if (! empty($c->organo_website)) {
                        $contactData[] = ['type' => 'website', 'value' => $c->organo_website];
                    }
                    foreach ($contactData as $contact) {
                        DB::table('contacts')->insert(array_merge($contact, [
                            'contactable_type' => 'Modules\\Contracts\\Models\\Organization',
                            'contactable_id' => $orgId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                    }
                }
            });

        // Set organization_id FK on contracts
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
        $seen = [];
        DB::table('contracts')
            ->whereNotNull('adjudicatario_nombre')
            ->where('adjudicatario_nombre', '!=', '')
            ->orderBy('id')
            ->chunk(1000, function ($contracts) use (&$seen) {
                foreach ($contracts as $c) {
                    $key = $c->adjudicatario_nif ?: md5($c->adjudicatario_nombre);
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;
                        DB::table('companies')->insertOrIgnore([
                            'name' => $c->adjudicatario_nombre,
                            'identifier' => $c->adjudicatario_nif,
                            'nif' => $c->adjudicatario_nif,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        // Create awards
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
                    if (! $companyId) {
                        $companyId = DB::table('companies')->where('name', $c->adjudicatario_nombre)->value('id');
                    }
                    if (! $companyId) {
                        continue;
                    }

                    DB::table('awards')->insertOrIgnore([
                        'contract_id' => $c->id,
                        'company_id' => $companyId,
                        'amount' => $c->importe_adjudicacion_con_iva ?? null,
                        'amount_without_tax' => $c->importe_adjudicacion_sin_iva ?? null,
                        'procedure_type' => $c->procedimiento_code ?? null,
                        'urgency' => $c->urgencia_code ?? null,
                        'award_date' => $c->fecha_adjudicacion ?? null,
                        'start_date' => $c->fecha_inicio ?? null,
                        'formalization_date' => $c->fecha_formalizacion ?? null,
                        'contract_number' => $c->contrato_numero ?? null,
                        'sme_awarded' => $c->sme_awarded ?? null,
                        'num_offers' => $c->num_ofertas ?? null,
                        'result_code' => $c->resultado_code ?? null,
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
