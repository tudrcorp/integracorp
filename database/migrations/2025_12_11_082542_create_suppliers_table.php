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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('status_convenio')->nullable();
            $table->string('tipo_clinica')->nullable();
            $table->string('tipo_servicio')->nullable();
            $table->string('city_id')->nullable();
            $table->string('state_id')->nullable();
            $table->string('clasificacion')->nullable();
            $table->longText('horario')->nullable();
            $table->string('status_sistema')->nullable();
            $table->string('rif')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('contacto_principal')->nullable();
            $table->string('correo_principal')->nullable();
            $table->string('afiliacion_proveedor')->nullable();
            $table->string('observaciones')->nullable();
            $table->string('ubicacion_principal')->nullable();
            $table->string('convenio_pago')->nullable();
            $table->string('tiempo_credito')->nullable();
            $table->string('departamento')->nullable();
            $table->string('auditado')->nullable();
            $table->string('fecha_auditoria')->nullable();
            $table->string('auditor')->nullable();
            $table->boolean('urgen_care')->nullable();
            $table->boolean('consulta_aps')->nullable();
            $table->boolean('amd')->nullable();
            $table->boolean('laboratorio_centro')->nullable();
            $table->boolean('laboratorio_domicilio')->nullable();
            $table->boolean('rx_centro')->nullable();
            $table->boolean('rx_domicilio')->nullable();
            $table->boolean('eco_abdominal_centro')->nullable();
            $table->boolean('eco_abdominal_domicilio')->nullable();
            $table->boolean('electrocardiograma_centro')->nullable();
            $table->boolean('electrocardiograma_domicilio')->nullable();
            $table->boolean('mamografia')->nullable();
            $table->boolean('tomografo')->nullable();
            $table->boolean('resonancia')->nullable();
            $table->boolean('encologogia')->nullable();
            $table->boolean('equipos_especiales_oftalmologia')->nullable();
            $table->boolean('radioterapia_intraoperatoria')->nullable();
            $table->boolean('quirofanos')->nullable();
            $table->boolean('uci_uten')->nullable();
            $table->boolean('neonatal')->nullable();
            $table->boolean('ambulancias')->nullable();
            $table->boolean('odontologia')->nullable();
            $table->boolean('oftalmologia')->nullable();
            $table->boolean('densitometria_osea')->nullable();
            $table->boolean('dialisis')->nullable();
            $table->boolean('otras_unidades_especiales')->nullable();
            $table->longText('otros_servicios')->nullable();
            $table->string('created_by');
            $table->string('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};