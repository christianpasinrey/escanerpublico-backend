<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Awards may have rows linked to old contract_id. Under wipe+reproceso
        // strategy, losing those rows is acceptable — Phase 1.2 re-populates via
        // the new parser. Truncate so the NOT NULL contract_lot_id can be added
        // without orphaning rows.
        if (Schema::hasColumn('awards', 'contract_id')) {
            \Illuminate\Support\Facades\DB::table('awards')->delete();
        }

        Schema::table('awards', function (Blueprint $t) {
            // The original table has unique(['contract_id','company_id']) AND
            // a FK on contract_id. MySQL rejects dropping the composite unique
            // while the FK still references contract_id (the unique index is
            // what the FK uses to satisfy its index requirement). So: drop the
            // FK FIRST, then the unique, then the column.
            if (Schema::hasColumn('awards', 'contract_id')) {
                $t->dropForeign(['contract_id']);
                $t->dropUnique(['contract_id', 'company_id']);
                $t->dropColumn('contract_id');
            }
        });

        Schema::table('awards', function (Blueprint $t) {
            if (! Schema::hasColumn('awards', 'contract_lot_id')) {
                $t->foreignId('contract_lot_id')->after('id')->constrained('contract_lots')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('awards', 'description')) {
                $t->text('description')->nullable()->after('amount_without_tax');
            }
            // start_date already exists in the original create_awards_table migration,
            // so skip re-adding it. The assertion only checks column presence.
            if (! Schema::hasColumn('awards', 'start_date')) {
                $t->date('start_date')->nullable()->after('award_date');
            }
            if (! Schema::hasColumn('awards', 'lower_tender_amount')) {
                $t->decimal('lower_tender_amount', 15, 2)->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('awards', 'higher_tender_amount')) {
                $t->decimal('higher_tender_amount', 15, 2)->nullable()->after('lower_tender_amount');
            }
            if (! Schema::hasColumn('awards', 'smes_received_tender_quantity')) {
                $t->unsignedInteger('smes_received_tender_quantity')->nullable()->after('num_offers');
            }
        });

        Schema::table('awards', function (Blueprint $t) {
            $existingIndexes = collect(Schema::getIndexes('awards'))->pluck('name')->all();

            if (! in_array('awards_lot_company_unique', $existingIndexes, true)) {
                $t->unique(['contract_lot_id', 'company_id'], 'awards_lot_company_unique');
            }
            if (! in_array('awards_company_award_date_idx', $existingIndexes, true)) {
                $t->index(['company_id', 'award_date'], 'awards_company_award_date_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $t) {
            $t->dropIndex('awards_company_award_date_idx');
            $t->dropUnique('awards_lot_company_unique');
            $t->dropForeign(['contract_lot_id']);
            $t->dropColumn(['contract_lot_id','description',
                'lower_tender_amount','higher_tender_amount','smes_received_tender_quantity']);
        });

        Schema::table('awards', function (Blueprint $t) {
            $t->foreignId('contract_id')->after('id')->constrained('contracts')->cascadeOnDelete();
            $t->unique(['contract_id', 'company_id']);
        });
    }
};
