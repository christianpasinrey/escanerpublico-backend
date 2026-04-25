<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_product_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('activity_code', 32)->nullable()->comment('CNAE/IAE code o NULL si aplica por keyword');
            $table->string('keyword', 255)->nullable()->comment('Para sectores no mapeables a CNAE: "pan", "libros", "medicamentos"');
            $table->string('rate_type', 16)->comment('general|reduced|super_reduced|special|zero|exempt');
            $table->decimal('rate', 5, 2)->comment('21.00, 10.00, 5.00, 4.00, 0.00');
            $table->text('description')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->index(['year', 'activity_code']);
            $table->index(['year', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_product_rates');
    }
};
