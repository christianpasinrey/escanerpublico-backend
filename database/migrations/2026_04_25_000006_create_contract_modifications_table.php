<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('contract_modifications')) {
            return;
        }

        Schema::create('contract_modifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->enum('type', ['modification', 'extension', 'cancellation', 'assignment', 'annulment']);
            $t->date('issue_date');
            $t->date('effective_date')->nullable();
            $t->text('description')->nullable();
            $t->decimal('amount_delta', 15, 2)->nullable();
            $t->date('new_end_date')->nullable();
            $t->foreignId('related_notice_id')->nullable()->constrained('contract_notices')->nullOnDelete();
            $t->timestamps();
            $t->unique(['contract_id', 'type', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_modifications');
    }
};
