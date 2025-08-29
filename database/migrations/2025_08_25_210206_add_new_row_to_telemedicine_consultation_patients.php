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
        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            $table->string('hombro_izq')->nullable();
            $table->string('hombro_der')->nullable();
            $table->string('hombro_comp')->nullable();
            $table->string('codo_izq')->nullable();
            $table->string('codo_der')->nullable();
            $table->string('codo_comp')->nullable();
            $table->string('muneca_izq')->nullable();
            $table->string('muneca_der')->nullable();
            $table->string('muneca_comp')->nullable();
            $table->string('mano_izq')->nullable();
            $table->string('mano_der')->nullable();
            $table->string('mano_comp')->nullable();
            $table->string('humero_izq')->nullable();
            $table->string('humero_der')->nullable();
            $table->string('humero_comp')->nullable();
            $table->string('ante_izq')->nullable();
            $table->string('ante_der')->nullable();
            $table->string('ante_comp')->nullable();
            $table->string('cdl_ap')->nullable();
            $table->string('pocep')->nullable();
            $table->string('cc_ap')->nullable();
            $table->string('cc_oblicuas')->nullable();
            $table->string('cc_la_flexion')->nullable();
            $table->string('cc_la_extension')->nullable();
            $table->string('cls_ap')->nullable();
            $table->string('cls_oblicuas')->nullable();
            $table->string('cls_la_flexion')->nullable();
            $table->string('cls_la_extension')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemedicine_consultation_patients', function (Blueprint $table) {
            //
        });
    }
};