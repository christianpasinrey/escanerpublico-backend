<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->string('identifier')->nullable()->index()->comment('DIR3 code');
            $table->string('nif', 20)->nullable()->index();
            $table->string('type_code', 10)->nullable();
            $table->json('hierarchy')->nullable();
            $table->string('parent_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
