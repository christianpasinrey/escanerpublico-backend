<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_regime_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regime_id')->constrained('tax_regimes')->cascadeOnDelete();
            $table->string('model_code', 16)->comment('303, 130, 100, 390, 349…');
            $table->string('periodicity', 24)->comment('monthly|quarterly|annual|on_event');
            $table->string('deadline_rule', 255)->nullable()->comment('Ej: "Día 20 del mes siguiente al fin de trimestre"');
            $table->text('description')->nullable();
            $table->boolean('electronic_required')->default(true);
            $table->boolean('certificate_required')->default(false);
            $table->boolean('draft_available')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->index(['regime_id', 'model_code']);
            $table->index(['model_code', 'periodicity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_regime_obligations');
    }
};
