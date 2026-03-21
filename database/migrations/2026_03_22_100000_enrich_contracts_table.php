<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Org contact info
            $table->string('organo_nif', 20)->nullable();
            $table->string('organo_website', 500)->nullable();
            $table->string('organo_telefono', 30)->nullable();
            $table->string('organo_email')->nullable();
            $table->string('organo_direccion', 500)->nullable();
            $table->string('organo_ciudad')->nullable();
            $table->string('organo_cp', 10)->nullable();
            $table->string('organo_tipo_code', 10)->nullable();

            // Org hierarchy
            $table->json('organo_jerarquia')->nullable();

            // TenderingProcess extras
            $table->string('submission_method_code', 10)->nullable();
            $table->string('contracting_system_code', 10)->nullable();
            $table->date('fecha_disponibilidad_docs')->nullable();
            $table->time('hora_presentacion_limite')->nullable();

            // TenderResult extras
            $table->boolean('sme_awarded')->nullable();
            $table->string('contrato_numero')->nullable();

            // TenderingTerms
            $table->json('criterios_adjudicacion')->nullable();
            $table->string('garantia_tipo_code', 10)->nullable();
            $table->decimal('garantia_porcentaje', 5, 2)->nullable();
            $table->string('idioma', 5)->nullable();

            // Contract extension
            $table->text('opciones_descripcion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'organo_nif', 'organo_website', 'organo_telefono', 'organo_email',
                'organo_direccion', 'organo_ciudad', 'organo_cp', 'organo_tipo_code',
                'organo_jerarquia', 'submission_method_code', 'contracting_system_code',
                'fecha_disponibilidad_docs', 'hora_presentacion_limite',
                'sme_awarded', 'contrato_numero', 'criterios_adjudicacion',
                'garantia_tipo_code', 'garantia_porcentaje', 'idioma', 'opciones_descripcion',
            ]);
        });
    }
};
