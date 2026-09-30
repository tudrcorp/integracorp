<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos e imágenes que el médico carga desde el asistente de consulta
 * (consulta inicial o seguimiento) y que se muestran en la bitácora del caso.
 */
return new class extends Migration
{
    private const TABLE = 'telemedicine_case_attachments';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_case_id');
            $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable();
            $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
            $table->string('stage', 30);
            $table->unsignedSmallInteger('follow_up_number')->nullable();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['telemedicine_case_id', 'created_at'], 'tm_case_attachments_case_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
