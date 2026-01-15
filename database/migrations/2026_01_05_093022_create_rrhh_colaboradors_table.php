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
        Schema::create('rrhh_colaboradors', function (Blueprint $table) {
            $table->id();
            $table->string('fullName')->nullable();
            $table->string('departmento_id')->nullable();
            $table->string('cargo_id')->nullable();
            $table->string('cedula')->nullable();
            $table->string('sexo')->nullable();
            $table->string('fechaNacimiento')->nullable();
            $table->string('fechaIngreso')->nullable();
            $table->string('telefono')->nullable();
            $table->string('telefonoCorporativo')->nullable();
            $table->string('emailCorporativo')->nullable();
            $table->string('emailAlternativo')->nullable();
            $table->string('emailPersonal')->nullable();
            $table->string('direccion')->nullable();
            $table->string('nroHijos')->nullable();
            $table->string('nroHijoDependiente')->nullable();
            $table->string('tallaCamisa')->nullable();
            $table->string('banck_id')->nullable();
            $table->string('nroCta')->nullable();
            $table->string('codigoCta')->nullable();
            $table->string('tipoCta')->nullable();
            $table->string('status')->default('activo');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rrhh_colaboradors');
    }
};
