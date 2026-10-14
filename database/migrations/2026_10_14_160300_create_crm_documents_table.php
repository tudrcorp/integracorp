<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF aprobados que el analista envía con un clic (beneficios, red de clínicas, condiciones).
 * El archivo vive en el disco privado; aquí solo la referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_documents')) {
            return;
        }

        Schema::create('crm_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('area', 40)->nullable();
            $table->string('title', 80);
            $table->string('description', 200)->nullable();
            $table->string('path', 255);
            $table->string('filename', 120);
            $table->unsignedInteger('size')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['active', 'area'], 'crm_doc_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_documents');
    }
};
