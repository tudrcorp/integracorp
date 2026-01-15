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
        Schema::create('rrhh_detalle_prestamos', function (Blueprint $table) {
            $table->id();
            $table->integer('colaborador_id');
            $table->integer('prestamo_id');
            $table->integer('nro_cuota_cancelada')->nullable();
            $table->decimal('monto_cuota',10,2)->default(0.00);
            $table->decimal('saldo_deudor',10,2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rrhh_detalle_prestamos');
    }
};
