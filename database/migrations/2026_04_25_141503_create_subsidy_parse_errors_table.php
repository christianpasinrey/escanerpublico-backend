<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_parse_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->nullable()->index();
            $table->string('source', 20)->default('BDNS');
            $table->string('field', 100)->nullable();
            $table->string('message', 500);
            $table->text('raw_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_parse_errors');
    }
};
