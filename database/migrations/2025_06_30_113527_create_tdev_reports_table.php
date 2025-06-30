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
            $table->string('fecha');
            $table->string('vaucher');
            $table->string('agente');
            $table->string('subagente')->nullable();
            $table->string('salida');
            $table->string('regreso');
            $table->string('fecha_anulacion');
            $table->string('pasajero');
            $table->string('nacionalidad');
            $table->string('tipo_documento');
            $table->string('nro_documento');
            $table->string('categoria_del_plan');
            $table->string('descripcion_del_plan');
            $table->string('origen_del_viaje');
            $table->string('nro_dias_de_servicio');
            $table->string('edad');
            $table->string('estatus_del_vaucher');
            $table->string('referencia');
            $table->string('plan_familiar');
            $table->decimal('descuento', 8, 2);
            $table->decimal('impuesto', 8, 2);
            $table->decimal('precio_upgrade', 8, 2);
            $table->decimal('precio_de_venta', 8, 2);
            
            //Campos agregados al reporte
            //-----------------------------------------------------------
            $table->decimal('total_precio_venta', 8, 2)->default(0.00);
            $table->string('fecha_pago_vaucher')->nullable();
            $table->string('forma_de_pago')->nullable();
            $table->string('entidad_bancaria_receptora')->nullable();
            $table->string('referencia_bancaria')->nullable();
            $table->decimal('tasa_pago', 8, 2)->default(0.00);
            $table->decimal('monto_abonado_en_cuenta', 8, 2)->default(0.00);
            $table->string('estatus_pago')->nullable();
            $table->string('dias_emision')->nullable();
            $table->decimal('porcen_comision', 8, 2)->default(0.00);
            $table->decimal('comision_agencia', 8, 2)->default(0.00);
            $table->decimal('comision_agente', 8, 2)->default(0.00);
            $table->decimal('comision_subagente', 8, 2)->default(0.00);
            $table->decimal('monto_comision', 8, 2)->default(0.00);
            $table->string('estatus_comision')->nullable();
            $table->string('fecha_pago_comision')->nullable();
            $table->string('referencia_bancaria_comision')->nullable();
            $table->string('relacion_comision')->nullable();
            $table->string('observaciones')->nullable();
            $table->decimal('neto_del_servicio', 8, 2)->default(0.00);
            $table->decimal('utilidad_tdev', 8, 2)->default(0.00);
            //------------------------------------------------------------
            
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