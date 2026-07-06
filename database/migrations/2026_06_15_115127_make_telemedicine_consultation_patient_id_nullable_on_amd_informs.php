<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemedicine_amd_informs', function (Blueprint $table) {
            $table->dropForeign('amd_informs_consultation_fk');
        });

        Schema::table('telemedicine_amd_informs', function (Blueprint $table) {
            $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable()->change();

            $table->foreign('telemedicine_consultation_patient_id', 'amd_informs_consultation_fk')
                ->references('id')
                ->on('telemedicine_consultation_patients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telemedicine_amd_informs', function (Blueprint $table) {
            $table->dropForeign('amd_informs_consultation_fk');
        });

        Schema::table('telemedicine_amd_informs', function (Blueprint $table) {
            $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable(false)->change();

            $table->foreign('telemedicine_consultation_patient_id', 'amd_informs_consultation_fk')
                ->references('id')
                ->on('telemedicine_consultation_patients')
                ->cascadeOnDelete();
        });
    }
};
