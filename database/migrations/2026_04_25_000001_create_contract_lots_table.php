<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $t->unsignedInteger('lot_number');
            $t->string('title', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('tipo_contrato_code', 10)->nullable();
            $t->string('subtipo_contrato_code', 10)->nullable();
            $t->json('cpv_codes')->nullable();
            $t->decimal('budget_with_tax', 15, 2)->nullable();
            $t->decimal('budget_without_tax', 15, 2)->nullable();
            $t->decimal('estimated_value', 15, 2)->nullable();
            $t->decimal('duration', 8, 2)->nullable();
            $t->string('duration_unit', 10)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('nuts_code', 10)->nullable();
            $t->string('lugar_ejecucion', 255)->nullable();
            $t->text('options_description')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'lot_number']);
            $t->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_lots');
    }
};
