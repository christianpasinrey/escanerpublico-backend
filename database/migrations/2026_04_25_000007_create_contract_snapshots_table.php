<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('contract_snapshots')) {
            return;
        }

        Schema::create('contract_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->timestamp('entry_updated_at');
            $t->string('status_code', 5);
            $t->char('content_hash', 40);
            $t->json('payload')->nullable();
            $t->string('source_atom', 500)->nullable();
            $t->timestamp('ingested_at');
            $t->timestamps();
            $t->unique(['contract_id', 'entry_updated_at']);
            $t->index(['contract_id', 'entry_updated_at'], 'snapshots_contract_updated_idx');
            $t->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_snapshots');
    }
};
