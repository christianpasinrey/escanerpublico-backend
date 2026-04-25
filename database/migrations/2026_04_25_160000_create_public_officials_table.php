<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_officials', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('normalized_name')->unique();
            $table->string('honorific', 20)->nullable();
            $table->integer('appointments_count')->default(0)->index();
            $table->date('first_appointment_date')->nullable();
            $table->date('last_event_date')->nullable()->index();
            $table->timestamps();

            $table->fullText('full_name', 'ft_full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_officials');
    }
};
