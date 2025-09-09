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
        Schema::create('capemiacs', function (Blueprint $table) {
            $table->id();
            $table->string('cliente');
            $table->string('segmento');
            $table->string('rif');
            $table->string('telefonoUno');
            $table->string('telefonoDos');
            $table->string('telefonoTres');
            $table->string('email');
            $table->string('fecha_registro');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capemiacs');
    }
};