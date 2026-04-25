<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autonomo_brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('bracket_number')->comment('1..15 según RD-ley 13/2022');
            $table->decimal('from_yield', 14, 2)->comment('Rendimiento neto mensual desde');
            $table->decimal('to_yield', 14, 2)->nullable()->comment('Rendimiento neto mensual hasta. NULL = sin tope');
            $table->decimal('base_min', 14, 2)->comment('Base de cotización mínima del tramo');
            $table->decimal('base_max', 14, 2)->comment('Base de cotización máxima del tramo');
            $table->decimal('monthly_quota_min', 14, 2)->comment('Cuota mensual mínima del tramo');
            $table->decimal('monthly_quota_max', 14, 2)->comment('Cuota mensual máxima del tramo');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['year', 'bracket_number'], 'uniq_autonomo_bracket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autonomo_brackets');
    }
};
