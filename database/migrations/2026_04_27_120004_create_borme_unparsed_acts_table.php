<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borme_unparsed_acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borme_pdf_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('entry_number')->nullable();
            $table->mediumText('raw_text');
            $table->string('reason', 128);
            $table->string('parser_version', 32);
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index('reason');
            $table->index(['borme_pdf_id', 'entry_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borme_unparsed_acts');
    }
};
