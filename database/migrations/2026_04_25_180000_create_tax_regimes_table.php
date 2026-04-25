<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_regimes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->comment('EDN, EDS, EO, IVA_GEN, IVA_CAJA, IS_GEN, RG, RETA…');
            $table->string('scope', 8)->comment('irpf|iva|is|ss');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->json('requirements')->nullable()->comment('Umbrales, exclusiones, condiciones');
            $table->string('model_quarterly', 16)->nullable()->comment('130, 131, 303, 311…');
            $table->string('model_annual', 16)->nullable()->comment('100, 390, 200…');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('legal_reference_url', 500)->nullable();
            $table->char('source_hash', 64)->nullable();
            $table->longText('editorial_md')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'code']);
            $table->index(['scope', 'valid_from', 'valid_to'], 'idx_regimes_scope_validity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_regimes');
    }
};
