<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $t->dropColumn(['buyer_profile_uri', 'activity_code', 'mix_contract_indicator',
                'funding_program_code', 'over_threshold_indicator', 'national_legislation_code',
                'received_appeal_quantity', 'snapshot_updated_at', 'annulled_at']);
        });
    }
};
