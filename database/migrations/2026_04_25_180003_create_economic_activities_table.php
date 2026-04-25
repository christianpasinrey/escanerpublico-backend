<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_activities', function (Blueprint $table) {
            $table->id();
            $table->string('system', 8)->comment('cnae|iae');
            $table->string('code', 32)->comment('CNAE-2025: A, A01, A011, A0111, A01110 — IAE: 1, 11, 111, 1111…');
            $table->string('parent_code', 32)->nullable();
            $table->unsignedTinyInteger('level')->comment('1=section, 2=division, 3=group, 4=class, 5=subclass');
            $table->string('name', 500);
            $table->string('section', 8)->nullable()->comment('Letra de sección CNAE / Sección IAE 1|2|3');
            $table->unsignedSmallInteger('year');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->longText('editorial_md')->nullable();
            $table->timestamps();

            $table->unique(['system', 'code', 'year'], 'uniq_activity');
            $table->index(['system', 'parent_code']);
            $table->index(['system', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('economic_activities');
    }
};
