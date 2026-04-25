<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_type_id')->constrained('tax_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('region_code', 8)->nullable();
            $table->decimal('rate', 7, 4)->nullable()->comment('Porcentaje. NULL si no es porcentaje fijo');
            $table->decimal('base_min', 14, 2)->nullable();
            $table->decimal('base_max', 14, 2)->nullable();
            $table->decimal('fixed_amount', 14, 2)->nullable()->comment('Para tasas con cuantía fija');
            $table->json('conditions')->nullable()->comment('Condiciones de aplicación');
            $table->string('source_url', 500)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tax_type_id', 'year', 'region_code'], 'idx_rates_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
