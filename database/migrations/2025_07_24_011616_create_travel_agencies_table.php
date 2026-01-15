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
        Schema::create('travel_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->string('fechaIngreso')->nullable();
            $table->string('representante')->nullable();
            $table->string('idRepresentante')->nullable();
            $table->string('FechaNacimientoRepresentante')->nullable();
            $table->string('name')->nullable();
            $table->string('typeIdentification')->nullable();
            $table->string('numberIdentification')->nullable();
            $table->string('userPortalWeb')->nullable();
            $table->string('aniversary')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('phoneAdditional')->nullable();
            $table->string('email')->nullable();
            $table->string('userInstagram')->nullable();
            $table->string('classification')->nullable();
            $table->decimal('comision', 10, 2)->nullable();
            $table->decimal('montoCreditoAprobado', 10, 2)->nullable();
            $table->string('nivel')->nullable();
            $table->string('agenteSuperiorNivel3')->nullable();
            $table->string('agenciaSuperiorNivel2')->nullable();
            $table->string('agenciaPpalNivel1')->nullable();
            $table->string('createdBy')->nullable();
            $table->string('updatedBy')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_agencies');
    }
};
