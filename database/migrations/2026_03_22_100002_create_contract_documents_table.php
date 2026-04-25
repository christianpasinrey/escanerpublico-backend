<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);
            $table->string('name')->comment('Document filename/ID from XML');
            $table->string('uri', 1000)->nullable();
            $table->string('hash')->nullable()->comment('DocumentHash for verification');

            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_documents');
    }
};
