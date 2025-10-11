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
        Schema::table('telemedicine_history_patients', function (Blueprint $table) {
            $table->string('input_tension_alta')->nullable();
            $table->string('input_diabetes')->nullable();
            $table->string('input_asma')->nullable();
            $table->string('input_cardiacos')->nullable();
            $table->string('input_gastritis_ulceras')->nullable();
            $table->string('input_enfermedad_autoimmune')->nullable();
            $table->string('input_trombosis_embooleanas')->nullable();
            $table->string('input_fracturas')->nullable();
            $table->string('input_cancer')->nullable();
            $table->string('input_ftranfusiones_sanguineas')->nullable();
            $table->string('input_tiroides')->nullable();
            $table->string('input_hepatitis')->nullable();
            $table->string('input_moretones_frecuentes')->nullable();
            $table->string('input_psiquiatricas')->nullable();
            $table->string('input_tension_alta_app')->nullable();
            $table->string('input_diabetes_app')->nullable();
            $table->string('input_asma_app')->nullable();
            $table->string('input_cardiacos_app')->nullable();
            $table->string('input_gastritis_ulceras_app')->nullable();
            $table->string('input_enfermedad_autoimmune_app')->nullable();
            $table->string('input_trombosis_embooleanas_app')->nullable();
            $table->string('input_fracturas_app')->nullable();
            $table->string('input_cancer_app')->nullable();
            $table->string('input_ftranfusiones_sanguineas_app')->nullable();
            $table->string('input_tiroides_app')->nullable();
            $table->string('input_hepatitis_app')->nullable();
            $table->string('input_moretones_frecuentes_app')->nullable();
            $table->string('input_psiquiatricas_app')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemedicine_history_patients', function (Blueprint $table) {
            //
        });
    }
};