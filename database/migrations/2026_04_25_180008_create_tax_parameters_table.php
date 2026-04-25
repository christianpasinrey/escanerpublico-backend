<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('region_code', 8)->nullable();
            $table->string('key', 128)->comment('irpf.minimo_personal, ss.tope_max_base, autonomo.cuota_minima…');
            $table->json('value')->comment('Valor numérico, objeto o array según corresponda');
            $table->string('source_url', 500)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'region_code', 'key'], 'uniq_param');
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_parameters');
    }
};
