<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_regime_compatibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regime_a_id')->constrained('tax_regimes')->cascadeOnDelete();
            $table->foreignId('regime_b_id')->constrained('tax_regimes')->cascadeOnDelete();
            $table->string('compatibility', 16)->comment('required|exclusive|optional');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['regime_a_id', 'regime_b_id'], 'uniq_regime_pair');
            $table->index('compatibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_regime_compatibility');
    }
};
