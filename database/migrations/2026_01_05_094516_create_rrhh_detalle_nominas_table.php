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
        Schema::create('rrhh_detalle_nominas', function (Blueprint $table) {
            $table->id();
            $table->integer('colaborador_id');
            $table->integer('nomina_id');
            $table->integer('cargo_id');
            $table->integer('departamento_id');
            $table->decimal('salario', 10, 2);
            $table->decimal('monto_descuento', 10, 2)->default(0.00);
            $table->decimal('monto_bono', 10, 2)->default(0.00);
            $table->decimal('monto_prestamo', 10, 2)->default(0.00);
            $table->integer('nro_cuota_cancelada')->nullable();
            $table->decimal('monto_otros', 10, 2)->default(0.00);
            $table->decimal('monto_total', 10, 2)->default(0.00);
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rrhh_detalle_nominas');
    }
};
