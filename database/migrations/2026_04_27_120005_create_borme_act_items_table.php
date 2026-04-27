<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_act_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borme_entry_id')->constrained()->cascadeOnDelete();
            $table->string('act_type', 64)->index();
            $table->json('payload');
            $table->date('effective_date')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borme_act_items');
    }
};
