<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_calls', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('BDNS')->index();
            $table->unsignedBigInteger('external_id')->comment('BDNS id de convocatoria');
            $table->string('numero_convocatoria', 50)->nullable()->index();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('description_cooficial')->nullable();
            $table->date('reception_date')->nullable()->index();
            $table->string('nivel1', 50)->nullable()->index()->comment('LOCAL/AUTONOMICA/ESTATAL');
            $table->string('nivel2', 255)->nullable();
            $table->string('nivel3', 255)->nullable();
            $table->string('codigo_invente', 50)->nullable();
            $table->boolean('is_mrr')->default(false)->comment('Mecanismo Recuperación y Resiliencia');
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('ingested_at')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->fullText('description', 'ft_description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_calls');
    }
};
