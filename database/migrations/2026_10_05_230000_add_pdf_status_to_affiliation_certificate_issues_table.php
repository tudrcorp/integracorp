<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los certificados corporativos grandes (cientos de carnets) se dibujan en cola:
 * aquí queda el estado del PDF y dónde se guardó. Los pequeños se siguen dibujando
 * al vuelo y dejan estas columnas vacías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliation_certificate_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_status')) {
                $table->string('pdf_status', 20)->nullable()->after('carnet_affiliate_ids');
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('pdf_status');
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_error')) {
                $table->text('pdf_error')->nullable()->after('pdf_path');
            }

            if (! Schema::hasColumn('affiliation_certificate_issues', 'pdf_generated_at')) {
                $table->timestamp('pdf_generated_at')->nullable()->after('pdf_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliation_certificate_issues', function (Blueprint $table) {
            foreach (['pdf_generated_at', 'pdf_error', 'pdf_path', 'pdf_status'] as $column) {
                if (Schema::hasColumn('affiliation_certificate_issues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
