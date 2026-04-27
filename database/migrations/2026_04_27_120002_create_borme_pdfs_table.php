<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borme_ingest_run_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->index();
            $table->unsignedSmallInteger('bulletin_no')->nullable();
            $table->string('cve', 64)->unique();
            $table->string('section', 1)->index();
            $table->string('province_ine', 2)->nullable()->index();
            $table->string('province_name', 64)->nullable();
            $table->string('source_url', 512);
            $table->string('pdf_sha256', 64)->nullable()->unique();
            $table->dateTime('downloaded_at')->nullable();
            $table->dateTime('parsed_at')->nullable();
            $table->enum('status', ['pending', 'downloaded', 'parsed', 'failed', 'skipped'])->default('pending')->index();
            $table->string('parser_version', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['date', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borme_pdfs');
    }
};
