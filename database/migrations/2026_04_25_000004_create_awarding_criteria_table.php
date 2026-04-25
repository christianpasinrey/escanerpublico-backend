<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awarding_criteria', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_lot_id')->constrained('contract_lots')->cascadeOnDelete();
            $t->string('type_code', 5);
            $t->string('subtype_code', 10)->nullable();
            $t->text('description');
            $t->text('note')->nullable();
            $t->decimal('weight_numeric', 8, 2)->nullable();
            $t->unsignedInteger('sort_order');
            $t->timestamps();
            $t->unique(['contract_lot_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awarding_criteria');
    }
};
