<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_parameter_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_legislation_norm_id')
                ->nullable()
                ->constrained('legislation_norms')
                ->nullOnDelete();
            $table->string('suggested_action', 255);
            $table->string('status', 16)
                ->default('pending')
                ->comment('pending|reviewed|applied|dismissed');
            $table->string('matched_pattern', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_legislation_norm_id', 'matched_pattern'],
                'uniq_alert_norm_pattern',
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_parameter_alerts');
    }
};
