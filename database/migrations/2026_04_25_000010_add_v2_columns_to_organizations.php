<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            if (!Schema::hasColumn('organizations', 'buyer_profile_uri')) {
                $t->string('buyer_profile_uri', 500)->nullable()->after('name');
            }
            if (!Schema::hasColumn('organizations', 'activity_code')) {
                $t->string('activity_code', 10)->nullable()->after('buyer_profile_uri');
            }
            if (!Schema::hasColumn('organizations', 'platform_id')) {
                $t->string('platform_id', 50)->nullable()->after('activity_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $cols = [];
            foreach (['buyer_profile_uri', 'activity_code', 'platform_id'] as $c) {
                if (Schema::hasColumn('organizations', $c)) {
                    $cols[] = $c;
                }
            }
            if (!empty($cols)) {
                $t->dropColumn($cols);
            }
        });
    }
};
