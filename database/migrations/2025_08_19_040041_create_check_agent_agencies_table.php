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
        Schema::create('check_agent_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('codificacion_agente');
            $table->string('codigo_agente');
            $table->string('nombre_agencia_agente');
            $table->string('nombre_representante');
            $table->string('nro_identificacion');
            $table->string('fecha_nacimiento');
            $table->string('fecha_ingreso');
            $table->string('estatus');
            $table->string('email');
            $table->string('telefono');
            $table->string('usuario_instagram');
            $table->string('pais');
            $table->string('estado');
            $table->string('ciudad');
            $table->string('tdec');
            $table->string('tdev');
            $table->string('tipo_agente');
            $table->string('agente_supervisor');
            $table->string('agencia_master');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_agent_agencies');
    }
};