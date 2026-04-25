<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->comment('IRPF, IS, IVA, ITP, AJD, ISD, IIEE_HIDROCARBUROS, TASA_DNI, TASA_JUDICIAL, IBI…');
            $table->string('scope', 16)->comment('state|regional|local');
            $table->string('levy_type', 16)->comment('impuesto|tasa|contribucion');
            $table->string('name', 255);
            $table->string('base_law_url', 500)->nullable();
            $table->string('region_code', 8)->nullable()->comment('ISO 3166-2:ES sin prefijo: MD, CT, AN…');
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->longText('editorial_md')->nullable();
            $table->timestamps();

            $table->unique(['code', 'scope', 'region_code'], 'uniq_tax_type');
            $table->index(['scope', 'levy_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_types');
    }
};
