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
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('coverage_zone')->nullable();
            $table->integer('supplier_clasificacion_id')->nullable();
            $table->string('tipo_clinica')->nullable();
            $table->string('horario')->nullable();
            $table->string('status_convenio')->nullable();
            $table->string('status_sistema')->nullable();
            $table->string('name')->nullable();
            $table->string('rif')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('personal_phone')->nullable();
            $table->string('local_phone')->nullable();
            $table->string('correo_principal')->nullable();
            $table->string('afiliacion_proveedor')->nullable();
            $table->string('ubicacion_principal')->nullable();
            $table->string('convenio_pago')->nullable();
            $table->string('tiempo_credito')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('speciality')->nullable();
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
