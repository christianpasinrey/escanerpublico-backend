<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('registry_letter', 2)->nullable()->after('nif');
            $table->unsignedBigInteger('registry_sheet')->nullable()->after('registry_letter');
            $table->string('registry_section', 8)->nullable()->after('registry_sheet');
            $table->string('legal_form', 16)->nullable()->after('registry_section');
            $table->text('domicile_address')->nullable()->after('legal_form');
            $table->string('domicile_city')->nullable()->after('domicile_address');
            $table->unsignedBigInteger('capital_cents')->nullable()->after('domicile_city');
            $table->string('capital_currency', 3)->nullable()->after('capital_cents');
            $table->date('incorporation_date')->nullable()->after('capital_currency');
            $table->string('status', 32)->nullable()->after('incorporation_date');
            $table->date('last_act_date')->nullable()->after('status');
            $table->json('source_modules')->nullable()->after('last_act_date');

            $table->index(['registry_letter', 'registry_sheet'], 'companies_registry_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_registry_idx');
            $table->dropIndex(['status']);
            $table->dropColumn([
                'registry_letter',
                'registry_sheet',
                'registry_section',
                'legal_form',
                'domicile_address',
                'domicile_city',
                'capital_cents',
                'capital_currency',
                'incorporation_date',
                'status',
                'last_act_date',
                'source_modules',
            ]);
        });
    }
};
