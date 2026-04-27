<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_ingest_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['daily', 'range', 'replay'])->default('daily');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->date('cursor_date')->nullable();
            $table->integer('total_pdfs')->default(0);
            $table->integer('processed_pdfs')->default(0);
            $table->integer('failed_pdfs')->default(0);
            $table->integer('processed_entries')->default(0);
            $table->integer('failed_entries')->default(0);
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
        Schema::dropIfExists('borme_ingest_runs');
    }
};
