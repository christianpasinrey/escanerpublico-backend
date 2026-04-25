<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidy_grant_id')->constrained()->cascadeOnDelete();
            $table->json('raw_payload');
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('fetched_at');
            $table->dateTime('source_updated_at')->nullable();
            $table->timestamps();

            $table->index(['subsidy_grant_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_snapshots');
    }
};
