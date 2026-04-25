<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_official_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boe_item_id')->constrained('boe_items')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('event_type', ['appointment', 'cessation', 'posession']);
            $table->string('cargo', 500)->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'effective_date']);
            $table->unique(['public_official_id', 'boe_item_id', 'event_type'], 'uk_official_item_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
