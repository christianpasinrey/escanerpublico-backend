<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reprocess_runs')) {
            Schema::create('reprocess_runs', function (Blueprint $t) {
                $t->id();
                $t->string('name', 100)->nullable();
                $t->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->unsignedInteger('total_atoms')->nullable();
                $t->unsignedInteger('processed_atoms')->default(0);
                $t->unsignedInteger('total_entries')->default(0);
                $t->unsignedInteger('failed_entries')->default(0);
                $t->json('config');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('reprocess_atom_runs')) {
            Schema::create('reprocess_atom_runs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('reprocess_run_id')->constrained('reprocess_runs')->cascadeOnDelete();
                $t->string('atom_path', 500);
                $t->char('atom_hash', 40);
                $t->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->unsignedInteger('entries_processed')->default(0);
                $t->unsignedInteger('entries_failed')->default(0);
                $t->text('error_message')->nullable();
                $t->timestamps();
                $t->index(['reprocess_run_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reprocess_atom_runs');
        Schema::dropIfExists('reprocess_runs');
    }
};
