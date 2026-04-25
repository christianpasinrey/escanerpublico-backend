<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_regime_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('economic_activities')->cascadeOnDelete();
            $table->json('eligible_regimes')->comment('Array de codes de tax_regimes válidos para esta actividad');
            $table->unsignedTinyInteger('vat_rate_default')->nullable()->comment('21|10|5|4|0');
            $table->decimal('irpf_retention_default', 5, 2)->nullable()->comment('Ej: 7.00, 15.00, 1.00, 19.00');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_regime_mappings');
    }
};
