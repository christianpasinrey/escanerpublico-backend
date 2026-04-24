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
            if (!Schema::hasColumn('contracts', 'criterios_adjudicacion')) {
                $t->json('criterios_adjudicacion')->nullable();
            }
        });
    }
};
