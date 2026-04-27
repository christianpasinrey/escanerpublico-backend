<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borme_pdf_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('entry_number');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name_raw');
            $table->string('company_name_normalized')->index();
            $table->string('legal_form', 16)->nullable();
            $table->string('registry_letter', 2)->nullable();
            $table->unsignedBigInteger('registry_sheet')->nullable();
            $table->string('registry_section', 8)->nullable();
            $table->string('registry_inscription', 32)->nullable();
            $table->date('registry_date')->nullable();
            $table->json('act_types')->nullable();
            $table->string('parser_version', 32);
            $table->dateTime('parsed_at');
            $table->mediumText('raw_text');
            $table->enum('resolution_status', ['matched', 'created', 'pending_review'])->default('matched')->index();
            $table->timestamps();

            $table->unique(['borme_pdf_id', 'entry_number']);
            $table->index(['registry_letter', 'registry_sheet'], 'borme_entries_registry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borme_entries');
    }
};
