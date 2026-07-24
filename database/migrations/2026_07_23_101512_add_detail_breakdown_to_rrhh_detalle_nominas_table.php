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
        Schema::table('rrhh_detalle_nominas', function (Blueprint $table) {
            if (! Schema::hasColumn('rrhh_detalle_nominas', 'colaborador_nombre')) {
                $table->string('colaborador_nombre')->nullable()->after('colaborador_id');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'colaborador_cedula')) {
                $table->string('colaborador_cedula')->nullable()->after('colaborador_nombre');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'departamento_nombre')) {
                $table->string('departamento_nombre')->nullable()->after('departamento_id');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'cargo_nombre')) {
                $table->string('cargo_nombre')->nullable()->after('cargo_id');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'salario_ves')) {
                $table->decimal('salario_ves', 14, 2)->default(0)->after('salario');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'monto_descuento_ves')) {
                $table->decimal('monto_descuento_ves', 14, 2)->default(0)->after('monto_descuento');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'monto_bono_ves')) {
                $table->decimal('monto_bono_ves', 14, 2)->default(0)->after('monto_bono');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'monto_prestamo_ves')) {
                $table->decimal('monto_prestamo_ves', 14, 2)->default(0)->after('monto_prestamo');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'monto_total_ves')) {
                $table->decimal('monto_total_ves', 14, 2)->default(0)->after('monto_total');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'detalle_asignaciones')) {
                $table->json('detalle_asignaciones')->nullable()->after('monto_total_ves');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'detalle_descuentos')) {
                $table->json('detalle_descuentos')->nullable()->after('detalle_asignaciones');
            }

            if (! Schema::hasColumn('rrhh_detalle_nominas', 'detalle_prestamos')) {
                $table->json('detalle_prestamos')->nullable()->after('detalle_descuentos');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rrhh_detalle_nominas', function (Blueprint $table) {
            foreach ([
                'colaborador_nombre',
                'colaborador_cedula',
                'departamento_nombre',
                'cargo_nombre',
                'salario_ves',
                'monto_descuento_ves',
                'monto_bono_ves',
                'monto_prestamo_ves',
                'monto_total_ves',
                'detalle_asignaciones',
                'detalle_descuentos',
                'detalle_prestamos',
            ] as $column) {
                if (Schema::hasColumn('rrhh_detalle_nominas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
