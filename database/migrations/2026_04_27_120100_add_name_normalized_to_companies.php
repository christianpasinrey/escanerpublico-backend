<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Borme\Services\Support\NameNormalizer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('name_normalized')->nullable()->after('name')->index();
        });

        // Backfill existing companies (typically PLACSP-sourced) with the same
        // normalization used by the BORME EntityResolver — without this, BORME
        // ingestion would create duplicate company rows for every name already
        // in the table because match keys would never align.
        $normalizer = new NameNormalizer;
        DB::table('companies')
            ->whereNotNull('name')
            ->whereNull('name_normalized')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($normalizer) {
                foreach ($rows as $row) {
                    DB::table('companies')
                        ->where('id', $row->id)
                        ->update(['name_normalized' => $normalizer->normalize($row->name)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['name_normalized']);
            $table->dropColumn('name_normalized');
        });
    }
};
