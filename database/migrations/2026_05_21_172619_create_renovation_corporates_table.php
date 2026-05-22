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
        Schema::create('renovation_corporates', function (Blueprint $table) {
            $table->id();
            // Campos de la tabla con relacion a las tablas affiliation_individual y affiliation_corporate
            $table->integer('affiliation_corporate_id');
            $table->date('date_renewal');
            $table->string('status');
            $table->string('created_by');
            $table->string('updated_by');
            $table->string('code_affiliation');
            $table->string('agent_id');
            $table->string('code_agency');
            $table->string('owner_code')->nullable();
            $table->string('owner_agent')->nullable();
            $table->json('info_renovation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renovation_corporates');
    }
};
