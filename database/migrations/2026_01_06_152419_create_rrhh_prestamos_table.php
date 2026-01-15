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
        Schema::create('rrhh_prestamos', function (Blueprint $table) {
            $table->id();
            $table->integer('colaborador_id');
            $table->string('descripcion');
            $table->decimal('monto', 10, 2)->default(0.00);
            $table->integer('nro_cuotas');
            $table->string('status');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rrhh_prestamos');
    }
};
