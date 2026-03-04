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
        Schema::create('operation_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('unit');
            $table->string('type');
            $table->integer('existence');
            $table->decimal('cost', 8, 2)->default(0);
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Campos para medicamentos y productos de salud
            $table->string('active_principle')->nullable()->comment('Principio activo (medicamentos)');
            $table->string('concentration')->nullable()->comment('Concentración ej. 500mg, 10mg/ml');
            $table->string('laboratory')->nullable()->comment('Laboratorio fabricante');
            $table->unsignedInteger('min_stock')->default(10)->comment('Stock mínimo para alertas');
            $table->boolean('is_active')->default(true);
            $table->string('category')->nullable()->comment('Categoría: medicamento, insumo, equipo médico');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_inventories');
    }
};
