<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Letra del documento (V, E, J, G) del titular y el pagador de una afiliación individual
 * y del RIF de una corporativa. Nulas: las afiliaciones existentes la siguen deduciendo
 * (AffiliationDocumentPrefix) hasta que un analista la elija.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliations', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliations', 'document_type_ti')) {
                $table->string('document_type_ti', 1)->nullable()->after('nro_identificacion_ti');
            }

            if (! Schema::hasColumn('affiliations', 'document_type_payer')) {
                $table->string('document_type_payer', 1)->nullable()->after('nro_identificacion_payer');
            }
        });

        Schema::table('affiliation_corporates', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliation_corporates', 'document_type_rif')) {
                $table->string('document_type_rif', 1)->nullable()->after('rif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliations', function (Blueprint $table): void {
            foreach (['document_type_ti', 'document_type_payer'] as $column) {
                if (Schema::hasColumn('affiliations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('affiliation_corporates', function (Blueprint $table): void {
            if (Schema::hasColumn('affiliation_corporates', 'document_type_rif')) {
                $table->dropColumn('document_type_rif');
            }
        });
    }
};
