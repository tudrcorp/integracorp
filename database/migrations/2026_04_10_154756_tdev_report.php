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
        Schema::create('tdev_reports', function (Blueprint $table) {
            $table->id();
            $table->string('mes')->nullable();
            $table->string('fecha');
            $table->string('vaucher');
            $table->string('agencia');
            $table->string('agente_emisor');
            $table->string('nivel')->nullable();
            $table->string('salida');
            $table->string('regreso');
            $table->string('pasajero');
            $table->string('nro_documento');
            $table->string('categoria_del_plan');
            $table->text('descripcion_del_plan');
            $table->string('estatus_vaucher');
            $table->string('cupon_de_descuento')->nullable();
            $table->string('cupon_comision')->nullable();
            $table->string('cupon_promocion')->nullable();
            $table->decimal('porcentaje_cupon', 10, 4)->nullable();
            $table->decimal('precio_upgrade', 12, 2)->default(0);
            $table->decimal('monto_pvp_precio_de_venta', 12, 2)->default(0);
            $table->string('forma_pago')->nullable();
            $table->string('entidad_bancaria_receptora')->nullable();
            $table->string('estatus_pago')->nullable();
            $table->string('referencia_bancaria_pago_vaucher_credito')->nullable();
            $table->decimal('tasa_bcv', 12, 4)->default(0);
            $table->decimal('monto_abonado_en_cuenta_vaucher_credito', 12, 2)->nullable();
            $table->string('fecha_pago_vaucher_credito')->nullable();
            $table->unsignedInteger('dias_transcurridos')->nullable();
            $table->decimal('porcentaje_comision', 10, 4)->nullable();
            $table->decimal('monto_comision', 10, 4)->nullable();
            $table->string('estatus_comision')->nullable();
            $table->string('fecha_pago_comision')->nullable();
            $table->string('formas_pago_comision')->nullable();
            $table->string('referencia_bancaria_comision')->nullable();
            $table->string('relacion_comision')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('comision_agencia', 12, 2)->default(0);
            $table->decimal('comision_agente', 12, 2)->default(0);
            $table->decimal('comision_subagente', 12, 2)->default(0);
            $table->decimal('neto_del_servicio', 12, 2)->default(0);
            $table->decimal('utilidad_tdev', 12, 2)->default(0);
            $table->string('status_report')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tdev_reports');
    }
};
