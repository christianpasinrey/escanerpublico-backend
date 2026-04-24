<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('parse_errors')) {
            return;
        }

        Schema::create('parse_errors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reprocess_atom_run_id')->nullable()->constrained('reprocess_atom_runs')->nullOnDelete();
            $t->string('atom_path', 500);
            $t->string('entry_external_id', 500)->nullable();
            $t->string('error_code', 50);
            $t->text('error_message');
            $t->text('raw_fragment')->nullable();
            $t->timestamps();
            $t->index('error_code');
            $t->index('reprocess_atom_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parse_errors');
    }
};
