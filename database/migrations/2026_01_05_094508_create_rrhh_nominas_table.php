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
        Schema::create('rrhh_nominas', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_salarios', 10, 2)->default(0.00);
            $table->decimal('total_descuentos',10,2)->default(0.00);
            $table->decimal('total_asignaciones',10,2)->default(0.00);
            $table->decimal('total_neto',10,2)->default(0.00);
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rrhh_nominas');
    }
};
