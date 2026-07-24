<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rrhh_asignacions', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_asignacions', 'tipo_valor')) {
                $table->string('tipo_valor')->default('monto')->after('description');
            }

            if (! Schema::hasColumn('rrhh_asignacions', 'porcentaje')) {
                $table->decimal('porcentaje', 8, 2)->nullable()->after('monto');
            }

            if (Schema::hasColumn('rrhh_asignacions', 'monto')) {
                $table->decimal('monto', 10, 2)->nullable()->change();
            }
        });

        Schema::table('rrhh_deduccions', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_deduccions', 'tipo_valor')) {
                $table->string('tipo_valor')->default('monto')->after('description');
            }

            if (! Schema::hasColumn('rrhh_deduccions', 'porcentaje')) {
                $table->decimal('porcentaje', 8, 2)->nullable()->after('monto');
            }

            if (Schema::hasColumn('rrhh_deduccions', 'monto')) {
                $table->decimal('monto', 10, 2)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_asignacions', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_asignacions', 'porcentaje')) {
                $table->dropColumn('porcentaje');
            }

            if (Schema::hasColumn('rrhh_asignacions', 'tipo_valor')) {
                $table->dropColumn('tipo_valor');
            }
        });

        Schema::table('rrhh_deduccions', function (Blueprint $table) {
            if (Schema::hasColumn('rrhh_deduccions', 'porcentaje')) {
                $table->dropColumn('porcentaje');
            }

            if (Schema::hasColumn('rrhh_deduccions', 'tipo_valor')) {
                $table->dropColumn('tipo_valor');
            }
        });
    }
};
