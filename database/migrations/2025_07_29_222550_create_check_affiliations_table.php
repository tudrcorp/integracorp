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
        Schema::create('check_affiliations', function (Blueprint $table) {
            $table->id();
            $table->integer('nro_afiliado')->nullable();
            $table->string('fecha_emision')->nullable();
            $table->string('codigo_tdec')->nullable();
            $table->string('tipo_plan')->nullable();
            $table->string('proveedor')->nullable();
            $table->string('nro_vaucher')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('tomador')->nullable();
            $table->string('tipo_doc')->nullable();
            $table->string('nro_doc')->nullable();
            $table->string('afiliado')->nullable();
            $table->string('tipo_doc_dos')->nullable();
            $table->string('nro_doc_tres')->nullable();
            $table->string('sexo')->nullable();
            $table->string('fecha_nacimiento')->nullable();
            $table->string('edad')->nullable();
            $table->string('parentesco')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('estado')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('direccion')->nullable();
            $table->string('vigencia_desde')->nullable();
            $table->string('vigencia_hasta')->nullable();
            $table->string('agencia')->nullable();
            $table->string('agente')->nullable();
            $table->string('plan')->nullable();
            $table->string('frecuencia_pago')->nullable();
            $table->string('forma_pago')->nullable();
            $table->string('monto_plan')->nullable();
            $table->string('monto_recibido')->nullable();
            $table->string('diferencia')->nullable();
            $table->string('estatus_pago')->nullable();
            $table->string('moneda')->nullable();
            $table->string('referencia')->nullable();
            $table->string('fecha_pago')->nullable();
            $table->string('pagado_desde')->nullable();
            $table->string('pagado_hasta')->nullable();
            $table->string('estatus_renovacion')->nullable();
            $table->string('estatus_afiliado')->nullable();
            $table->string('dias_para_vencer')->nullable();
            $table->string('estado_del_plan')->nullable();
            $table->string('pagado_ils_desde')->nullable();
            $table->string('pagado_ils_hasta')->nullable();
            $table->string('dia_vencimiento_ils')->nullable();
            $table->string('estado_plan_ils')->nullable();
            $table->string('fecha_egreso')->nullable();
            $table->longText('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_affiliations');
    }
};