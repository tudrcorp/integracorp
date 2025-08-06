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
        Schema::create('check_sales', function (Blueprint $table) {
            $table->id();
            $table->string('fecha')->nullable();
            $table->longText('agencia')->nullable();
            $table->string('agente')->nullable();
            $table->longText('nro_factura')->nullable();
            $table->longText('codgio_afiliado')->nullable();
            $table->longText('cliente_afiliado')->nullable();
            $table->string('contacto')->nullable();
            $table->string('rif')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('producto')->nullable();
            $table->string('servicio')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('poblacion')->nullable();
            $table->decimal('enero', 8, 2)->nullable();
            $table->decimal('febrero', 8, 2)->nullable();
            $table->decimal('marzo', 8, 2)->nullable();
            $table->decimal('abril', 8, 2)->nullable();
            $table->decimal('mayo', 8, 2)->nullable();
            $table->decimal('junio', 8, 2)->nullable();
            $table->decimal('agosto', 8, 2)->nullable();
            $table->decimal('septiembre', 8, 2)->nullable();
            $table->decimal('octubre', 8, 2)->nullable();
            $table->decimal('noviembre', 8, 2)->nullable();
            $table->decimal('diciembre', 8, 2)->nullable();
            $table->decimal('monto_pagado')->nullable();
            $table->longText('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_sales');
    }
};