<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            $table->string('notice_type_code', 30)->comment('TenderingNoticeTypeCode');
            $table->date('issue_date')->nullable()->comment('Publication date');
            $table->string('publication_media')->nullable()->comment('e.g. Perfil del Contratante');

            $table->string('document_type_code', 50)->nullable()->comment('e.g. ACTA_ADJ, ACTA_FORM');
            $table->string('document_type_name')->nullable()->comment('Human-readable doc type');
            $table->string('document_uri', 1000)->nullable();
            $table->string('document_filename')->nullable();

            $table->timestamps();

            $table->index(['contract_id', 'notice_type_code']);
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_notices');
    }
};
