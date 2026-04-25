<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_extraction_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boe_item_id')->constrained('boe_items')->cascadeOnDelete();
            $table->string('reason', 255);
            $table->text('raw_titulo')->nullable();
            $table->timestamps();

            $table->index('boe_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_extraction_errors');
    }
};
