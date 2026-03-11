<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('expediente', 500)->change();
            $table->string('external_id', 500)->change();
            $table->string('link', 1000)->nullable()->change();
            $table->string('organo_contratante', 500)->change();
            $table->string('organo_superior', 500)->nullable()->change();
            $table->string('adjudicatario_nombre', 500)->nullable()->change();
            $table->string('lugar_ejecucion', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revert — las columnas más grandes no causan problemas
    }
};
