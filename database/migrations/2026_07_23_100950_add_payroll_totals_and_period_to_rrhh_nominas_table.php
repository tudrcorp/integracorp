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
        Schema::table('rrhh_nominas', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_nominas', 'fecha_desde')) {
                $table->date('fecha_desde')->nullable()->after('id');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'fecha_hasta')) {
                $table->date('fecha_hasta')->nullable()->after('fecha_desde');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'tasa_bcv')) {
                $table->decimal('tasa_bcv', 12, 4)->nullable()->after('fecha_hasta');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_prestamos')) {
                $table->decimal('total_prestamos', 12, 2)->default(0)->after('total_asignaciones');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_salarios_ves')) {
                $table->decimal('total_salarios_ves', 14, 2)->default(0)->after('total_neto');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_descuentos_ves')) {
                $table->decimal('total_descuentos_ves', 14, 2)->default(0)->after('total_salarios_ves');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_asignaciones_ves')) {
                $table->decimal('total_asignaciones_ves', 14, 2)->default(0)->after('total_descuentos_ves');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_prestamos_ves')) {
                $table->decimal('total_prestamos_ves', 14, 2)->default(0)->after('total_asignaciones_ves');
            }

            if (! Schema::hasColumn('rrhh_nominas', 'total_neto_ves')) {
                $table->decimal('total_neto_ves', 14, 2)->default(0)->after('total_prestamos_ves');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_nominas', function (Blueprint $table) {
            foreach ([
                'fecha_desde',
                'fecha_hasta',
                'tasa_bcv',
                'total_prestamos',
                'total_salarios_ves',
                'total_descuentos_ves',
                'total_asignaciones_ves',
                'total_prestamos_ves',
                'total_neto_ves',
            ] as $column) {
                if (Schema::hasColumn('rrhh_nominas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
