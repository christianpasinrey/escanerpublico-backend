<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $existing = collect(DB::select(
            "SHOW INDEX FROM contracts WHERE Key_name = 'contracts_objeto_expediente_fulltext'"
        ));
        if ($existing->isEmpty()) {
            DB::statement(
                'ALTER TABLE contracts ADD FULLTEXT contracts_objeto_expediente_fulltext (objeto, expediente)'
            );
        }
    }

    public function down(): void
    {
        $existing = collect(DB::select(
            "SHOW INDEX FROM contracts WHERE Key_name = 'contracts_objeto_expediente_fulltext'"
        ));
        if ($existing->isNotEmpty()) {
            DB::statement('ALTER TABLE contracts DROP INDEX contracts_objeto_expediente_fulltext');
        }
    }
};
