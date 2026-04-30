<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_coordination_clinic_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_coordination_service_id')
                ->constrained('operation_coordination_services')
                ->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['operation_coordination_service_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_coordination_clinic_documents');
    }
};
