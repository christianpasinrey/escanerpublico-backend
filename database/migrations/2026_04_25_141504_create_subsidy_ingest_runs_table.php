<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_ingest_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['calls', 'grants']);
            $table->integer('cursor_page')->default(0);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->integer('total_pages')->nullable();
            $table->integer('total_elements')->nullable();
            $table->integer('processed_records')->default(0);
            $table->integer('failed_records')->default(0);
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'paused'])->default('pending')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_ingest_runs');
    }
};
