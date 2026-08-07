<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices orientados a la búsqueda global del panel Business.
 * Preferimos índices simples/compuestos en códigos e identificadores para LIKE prefijo y equality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->index('code', 'agencies_code_index');
            $table->index('rif', 'agencies_rif_index');
            $table->index('ci_responsable', 'agencies_ci_responsable_index');
            $table->index('name_corporative', 'agencies_name_corporative_index');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->index('code_agent', 'agents_code_agent_index');
            $table->index('ci', 'agents_ci_index');
            $table->index('rif', 'agents_rif_index');
            $table->index('name', 'agents_name_index');
        });

        Schema::table('affiliations', function (Blueprint $table): void {
            $table->index('code', 'affiliations_code_index');
            $table->index('nro_identificacion_ti', 'affiliations_nro_identificacion_ti_index');
            $table->index('nro_identificacion_payer', 'affiliations_nro_identificacion_payer_index');
            $table->index('full_name_ti', 'affiliations_full_name_ti_index');
        });

        Schema::table('affiliates', function (Blueprint $table): void {
            $table->index('nro_identificacion', 'affiliates_nro_identificacion_index');
            $table->index(['affiliation_id', 'nro_identificacion'], 'affiliates_affiliation_nro_identificacion_index');
        });

        Schema::table('affiliation_corporates', function (Blueprint $table): void {
            $table->index('code', 'affiliation_corporates_code_index');
            $table->index('rif', 'affiliation_corporates_rif_index');
            $table->index('name_corporate', 'affiliation_corporates_name_corporate_index');
        });

        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            $table->index('nro_identificacion', 'affiliate_corporates_nro_identificacion_index');
            $table->index(
                ['affiliation_corporate_id', 'nro_identificacion'],
                'affiliate_corporates_affiliation_nro_index',
            );
        });

        Schema::table('corporate_quotes', function (Blueprint $table): void {
            $table->index('code', 'corporate_quotes_code_index');
            $table->index('rif', 'corporate_quotes_rif_index');
            $table->index('full_name', 'corporate_quotes_full_name_index');
        });

        Schema::table('individual_quotes', function (Blueprint $table): void {
            $table->index('full_name', 'individual_quotes_full_name_index');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->index('name', 'companies_name_index');
            $table->index('rif', 'companies_rif_index');
        });

        Schema::table('white_companies', function (Blueprint $table): void {
            $table->index('name', 'white_companies_name_index');
            $table->index('rif', 'white_companies_rif_index');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropIndex('agencies_code_index');
            $table->dropIndex('agencies_rif_index');
            $table->dropIndex('agencies_ci_responsable_index');
            $table->dropIndex('agencies_name_corporative_index');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->dropIndex('agents_code_agent_index');
            $table->dropIndex('agents_ci_index');
            $table->dropIndex('agents_rif_index');
            $table->dropIndex('agents_name_index');
        });

        Schema::table('affiliations', function (Blueprint $table): void {
            $table->dropIndex('affiliations_code_index');
            $table->dropIndex('affiliations_nro_identificacion_ti_index');
            $table->dropIndex('affiliations_nro_identificacion_payer_index');
            $table->dropIndex('affiliations_full_name_ti_index');
        });

        Schema::table('affiliates', function (Blueprint $table): void {
            $table->dropIndex('affiliates_nro_identificacion_index');
            $table->dropIndex('affiliates_affiliation_nro_identificacion_index');
        });

        Schema::table('affiliation_corporates', function (Blueprint $table): void {
            $table->dropIndex('affiliation_corporates_code_index');
            $table->dropIndex('affiliation_corporates_rif_index');
            $table->dropIndex('affiliation_corporates_name_corporate_index');
        });

        Schema::table('affiliate_corporates', function (Blueprint $table): void {
            $table->dropIndex('affiliate_corporates_nro_identificacion_index');
            $table->dropIndex('affiliate_corporates_affiliation_nro_index');
        });

        Schema::table('corporate_quotes', function (Blueprint $table): void {
            $table->dropIndex('corporate_quotes_code_index');
            $table->dropIndex('corporate_quotes_rif_index');
            $table->dropIndex('corporate_quotes_full_name_index');
        });

        Schema::table('individual_quotes', function (Blueprint $table): void {
            $table->dropIndex('individual_quotes_full_name_index');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex('companies_name_index');
            $table->dropIndex('companies_rif_index');
        });

        Schema::table('white_companies', function (Blueprint $table): void {
            $table->dropIndex('white_companies_name_index');
            $table->dropIndex('white_companies_rif_index');
        });
    }
};
