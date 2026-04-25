<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('scope', 16)->comment('state|regional');
            $table->string('region_code', 8)->nullable();
            $table->string('type', 32)->comment('irpf_general|irpf_ahorro|retencion|autonomo|is…');
            $table->decimal('from_amount', 14, 2);
            $table->decimal('to_amount', 14, 2)->nullable()->comment('NULL = en adelante');
            $table->decimal('rate', 7, 4)->comment('Porcentaje aplicable al tramo');
            $table->decimal('fixed_amount', 14, 2)->nullable()->comment('Cuota fija acumulada hasta from_amount');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->index(['year', 'scope', 'region_code', 'type'], 'idx_brackets_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
