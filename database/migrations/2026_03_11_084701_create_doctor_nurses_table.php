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
        Schema::create('doctor_nurses', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('rif_ci')->unique();
            $table->integer('state_id');
            $table->integer('city_id');
            $table->string('clasificacion')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('estatus_sistemas')->nullable();
            $table->string('costo_promedio')->nullable();
            $table->string('contacto')->nullable();
            $table->string('email')->nullable();
            $table->string('fecha_afiliacion')->nullable();
            $table->longText('observaciones')->nullable();
            $table->boolean('tlm')->default(false);
            $table->boolean('amd')->default(false);
            $table->boolean('urg_in_situ')->default(false);
            $table->boolean('aps')->default(false);
            $table->boolean('equipo_orl')->default(false);
            $table->boolean('equipo_cirugia_menor');
            $table->boolean('electrocardiografo')->default(false);
            $table->boolean('nebulizador')->default(false);
            $table->boolean('glucometro')->default(false);
            $table->boolean('oximetro')->default(false);
            $table->boolean('tensiometro')->default(false);
            $table->boolean('oxigeno')->default(false);
            $table->boolean('otoscopio')->default(false);
            $table->boolean('esfigmomanometro_centro')->default(false);
            $table->boolean('esfigmomanometro_domicilio')->default(false);
            $table->string('contrato_grupo')->nullable();
            $table->string('disponibilidad')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('tipo_acuerdo')->nullable();
            $table->string('acuerdo_pago')->nullable();
            $table->string('tiempo_credito')->nullable();
            $table->string('departamento')->nullable();
            $table->string('datos_bancarios_nacionales')->nullable();
            $table->string('datos_bancarios_internacionales')->nullable();
            $table->string('zelle')->nullable();
            $table->string('baremos_tlm')->nullable();
            $table->string('baremos_amd')->nullable();
            $table->string('baremos_urg_in_situ')->nullable();
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
        Schema::dropIfExists('doctor_nurses');
    }
};
