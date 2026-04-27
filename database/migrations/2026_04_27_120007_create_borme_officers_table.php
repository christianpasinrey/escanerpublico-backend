<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borme_entry_id')->constrained()->cascadeOnDelete();
            $table->enum('officer_kind', ['person', 'company']);
            $table->foreignId('officer_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('officer_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('representative_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('role', 32)->index();
            $table->enum('action', ['appointment', 'cease', 'reelection', 'revocation'])->index();
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->index(['officer_person_id', 'action']);
            $table->index(['officer_company_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borme_officers');
    }
};
