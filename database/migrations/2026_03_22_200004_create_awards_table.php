<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('amount_without_tax', 15, 2)->nullable();
            $table->string('procedure_type', 10)->nullable();
            $table->string('urgency', 10)->nullable();
            $table->date('award_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('formalization_date')->nullable();
            $table->string('contract_number')->nullable();
            $table->boolean('sme_awarded')->nullable();
            $table->unsignedInteger('num_offers')->nullable();
            $table->string('result_code', 10)->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
