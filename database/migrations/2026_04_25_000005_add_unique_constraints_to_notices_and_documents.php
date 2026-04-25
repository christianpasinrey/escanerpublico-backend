<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // contract_notices: unique idempotency key + supporting index
        Schema::table('contract_notices', function (Blueprint $t) {
            $t->unique(
                ['contract_id', 'notice_type_code', 'issue_date'],
                'contract_notices_idempotency_unique'
            );
            $t->index(
                ['contract_id', 'issue_date'],
                'contract_notices_issue_date_idx'
            );
        });

        // contract_documents.uri is VARCHAR(1000) = 4000 bytes in utf8mb4.
        // MySQL InnoDB key-length limit is 3072 bytes, so we use a prefix
        // index on uri (512 chars = 2048 bytes) plus the FK column.
        // Laravel Blueprint does not expose prefix indexes, so we use raw SQL.
        $existing = collect(DB::select("SHOW INDEX FROM contract_documents WHERE Key_name = 'contract_documents_uri_unique'"));
        if ($existing->isEmpty()) {
            DB::statement(
                'CREATE UNIQUE INDEX contract_documents_uri_unique ON contract_documents (contract_id, uri(512))'
            );
        }
    }

    public function down(): void
    {
        Schema::table('contract_notices', function (Blueprint $t) {
            $t->dropUnique('contract_notices_idempotency_unique');
            $t->dropIndex('contract_notices_issue_date_idx');
        });

        $existing = collect(DB::select("SHOW INDEX FROM contract_documents WHERE Key_name = 'contract_documents_uri_unique'"));
        if ($existing->isNotEmpty()) {
            DB::statement('DROP INDEX contract_documents_uri_unique ON contract_documents');
        }
    }
};
