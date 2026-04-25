<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_security_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('regime', 16)->comment('RG|RETA|AGRARIO|HOGAR|MAR|MINERIA');
            $table->string('contingency', 32)->comment('contingencias_comunes|desempleo|fp|fogasa|mei|atep|cese_actividad…');
            $table->decimal('rate_employer', 6, 4)->nullable()->comment('Porcentaje a cargo de empresa');
            $table->decimal('rate_employee', 6, 4)->nullable()->comment('Porcentaje a cargo de trabajador');
            $table->decimal('base_min', 14, 2)->nullable();
            $table->decimal('base_max', 14, 2)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'regime', 'contingency'], 'uniq_ss_rate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_security_rates');
    }
};
