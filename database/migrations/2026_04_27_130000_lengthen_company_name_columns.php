<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BORME company names occasionally exceed VARCHAR(255). Promote `name` and
     * `name_normalized` to TEXT so ingestion never truncates legal names.
     * MySQL InnoDB cannot index TEXT without a prefix length, so existing
     * indexes are dropped first and recreated as 191-char prefix indexes.
     */
    public function up(): void
    {
        $this->dropIndexIfExists('companies', 'companies_name_index');
        $this->dropIndexIfExists('companies', 'companies_name_normalized_index');

        DB::statement('ALTER TABLE companies MODIFY COLUMN `name` TEXT NULL');
        DB::statement('ALTER TABLE companies MODIFY COLUMN `name_normalized` TEXT NULL');

        DB::statement('CREATE INDEX companies_name_index ON companies (`name`(191))');
        DB::statement('CREATE INDEX companies_name_normalized_index ON companies (`name_normalized`(191))');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('companies', 'companies_name_index');
        $this->dropIndexIfExists('companies', 'companies_name_normalized_index');

        DB::statement('ALTER TABLE companies MODIFY COLUMN `name` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE companies MODIFY COLUMN `name_normalized` VARCHAR(255) NULL');

        DB::statement('CREATE INDEX companies_name_index ON companies (`name`)');
        DB::statement('CREATE INDEX companies_name_normalized_index ON companies (`name_normalized`)');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );
        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        }
    }
};
