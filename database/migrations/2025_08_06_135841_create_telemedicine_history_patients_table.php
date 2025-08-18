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
        Schema::create('telemedicine_history_patients', function (Blueprint $table) {
            $table->id();
            $table->integer('telemedicine_patient_id');
            $table->string('cod_history')->unique();
            $table->string('user_id')->nullable();
            $table->string('cod_patient');
            $table->string('history_date');
            $table->string('weight');
            $table->string('height');
            /**
             * Campos agregados para mejorar la
             * historia del paciente
             */
            $table->string('reason');
            $table->string('current_illness');
            $table->string('strain');
            $table->string('temperature');
            $table->string('breaths');
            $table->string('pulse');
            $table->string('saturation');
            $table->string('condition');

            //Antecedentes Personales y Familiares
            $table->boolean('cancer')->nullable();
            $table->boolean('diabetes')->nullable();
            $table->boolean('tension_alta')->nullable();
            $table->boolean('cardiacos')->nullable();
            $table->boolean('psiquiatricas')->nullable();
            $table->boolean('alteraciones_coagulacion')->nullable();
            $table->boolean('trombosis_embooleanas')->nullable();
            $table->boolean('tranfusiones_sanguineas')->nullable();
            $table->boolean('COVID19')->nullable();

            //Antecedentes personales patológicos
            $table->boolean('hepatitis')->nullable();
            $table->boolean('VIH_SIDA')->nullable();
            $table->boolean('gastritis_ulceras')->nullable();
            $table->boolean('neurologia')->nullable();
            $table->boolean('ansiedad_angustia')->nullable();
            $table->boolean('tiroides')->nullable();
            $table->boolean('lupus')->nullable();
            $table->boolean('enfermedad_autoimmune')->nullable();
            $table->boolean('diabetes_mellitus')->nullable();
            $table->boolean('presion_arterial_alta')->nullable();
            $table->boolean('tiene_cateter_venoso')->nullable();
            $table->boolean('fracturas')->nullable();
            $table->boolean('trombosis_venosa')->nullable();
            $table->boolean('embooleania_pulmonar')->nullable();
            $table->boolean('varices_piernas')->nullable();
            $table->boolean('insuficiencia_arterial')->nullable();
            $table->boolean('coagulacion_anormal')->nullable();
            $table->boolean('moretones_frecuentes')->nullable();
            $table->boolean('sangrado_cirugias_previas')->nullable();
            $table->boolean('sangrado_cepillado_dental')->nullable();

            //Historia no patológica
            $table->boolean('alcohol')->nullable();
            $table->boolean('drogas')->nullable();
            $table->boolean('vacunas_recientes')->nullable();
            $table->boolean('transfusiones_sanguineas')->nullable();

            //Historia ginecologicos si aplica
            $table->string('edad_primera_menstruation')->nullable();
            $table->string('fecha_ultima_regla')->nullable();
            $table->integer('numero_embarazos')->nullable();
            $table->integer('numero_partos')->nullable();
            $table->integer('numero_abortos')->nullable();
            $table->integer('cesareas')->nullable();

            // alergias
            $table->json('allergies')->nullable();

            // antecedentes quirurjicos
            $table->longText('history_surgical')->nullable();

            // medicamentos y suplementos
            $table->longText('medications_supplements')->nullable();
            //observaciones
            $table->string('observations_ginecologica')->nullable();
            $table->string('observations_allergies')->nullable();
            $table->string('observations_medication')->nullable();
            $table->string('observations_diagnosis')->nullable();
            $table->string('observations_not_pathological')->nullable();
            $table->string('observations_pathological')->nullable();
            $table->string('created_by');
            
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemedicine_history_patients');
    }
};