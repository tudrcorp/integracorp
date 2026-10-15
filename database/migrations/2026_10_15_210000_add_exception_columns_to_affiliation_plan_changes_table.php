<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excepciones autorizadas al cambiar el plan (hoy: edad fuera de los rangos del Plan Ideal).
 * Quedan en columnas propias, no solo en el texto del motivo, para poder listarlas y auditarlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliation_plan_changes')) {
            return;
        }

        Schema::table('affiliation_plan_changes', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliation_plan_changes', 'exception_rule')) {
                $table->string('exception_rule', 40)->nullable()->after('reason')->index('aff_plan_changes_exception_idx');
            }

            if (! Schema::hasColumn('affiliation_plan_changes', 'exception_note')) {
                $table->string('exception_note', 1000)->nullable()->after('exception_rule');
            }

            if (! Schema::hasColumn('affiliation_plan_changes', 'exception_affiliates')) {
                $table->json('exception_affiliates')->nullable()->after('exception_note');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliation_plan_changes')) {
            return;
        }

        Schema::table('affiliation_plan_changes', function (Blueprint $table) {
            foreach (['exception_affiliates', 'exception_note', 'exception_rule'] as $column) {
                if (Schema::hasColumn('affiliation_plan_changes', $column)) {
                    if ($column === 'exception_rule') {
                        $table->dropIndex('aff_plan_changes_exception_idx');
                    }

                    $table->dropColumn($column);
                }
            }
        });
    }
};
