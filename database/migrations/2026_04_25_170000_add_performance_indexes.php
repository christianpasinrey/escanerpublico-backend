<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices para acelerar las queries de los listings index bajo carga concurrente.
 *
 * Análisis previo:
 *  - /contratos default ORDER BY snapshot_updated_at DESC sobre 10M rows hacía filesort
 *    completo (no había índice). Coste O(N log N) cada paginación.
 *  - /companies con withSum + withCount filtrado por amount < 1e9 hacía table scan
 *    en awards por cada compañía visible (no había composite company_id+amount).
 *  - /organismos/{id}/stats sumaba importes con índice solo en organization_id,
 *    aprovechando muy mal el merge con importe_con_iva.
 *
 * Migración online: usa ALGORITHM=INPLACE LOCK=NONE en MySQL 8 para no bloquear
 * writes. Si el motor no soporta, MySQL hará lock temporal pero la operación
 * sigue siendo idempotente (skip si el índice ya existe).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('contracts', 'idx_contracts_snapshot_updated', '(snapshot_updated_at)');
        $this->addIndex('contracts', 'idx_contracts_updated_at', '(updated_at)');
        $this->addIndex('contracts', 'idx_contracts_org_importe', '(organization_id, importe_con_iva)');
        $this->addIndex('contracts', 'idx_contracts_status_updated', '(status_code, snapshot_updated_at)');
        $this->addIndex('awards', 'idx_awards_company_amount', '(company_id, amount)');
        $this->addIndex('subsidy_grants', 'idx_subsidy_grants_grant_date', '(grant_date)');
        $this->addIndex('boe_items', 'idx_boe_items_seccion_fecha', '(seccion_code, fecha_publicacion)');
    }

    public function down(): void
    {
        $this->dropIndex('contracts', 'idx_contracts_snapshot_updated');
        $this->dropIndex('contracts', 'idx_contracts_updated_at');
        $this->dropIndex('contracts', 'idx_contracts_org_importe');
        $this->dropIndex('contracts', 'idx_contracts_status_updated');
        $this->dropIndex('awards', 'idx_awards_company_amount');
        $this->dropIndex('subsidy_grants', 'idx_subsidy_grants_grant_date');
        $this->dropIndex('boe_items', 'idx_boe_items_seccion_fecha');
    }

    private function addIndex(string $table, string $name, string $columns): void
    {
        if (! $this->tableExists($table)) {
            return;
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        // Intentamos online primero. Si la versión MySQL no lo soporta, fallback al modo por defecto.
        try {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` {$columns}, ALGORITHM=INPLACE, LOCK=NONE");
        } catch (\Throwable) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` {$columns}");
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->tableExists($table)) {
            return;
        }
        if (! $this->indexExists($table, $name)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`, ALGORITHM=INPLACE, LOCK=NONE");
        } catch (\Throwable) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
        }
    }

    private function tableExists(string $table): bool
    {
        $rows = DB::select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);

        return $rows !== [];
    }

    private function indexExists(string $table, string $name): bool
    {
        $rows = DB::select('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1', [$table, $name]);

        return $rows !== [];
    }
};
