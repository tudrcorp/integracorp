<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encuestas de Quality Control de Operaciones (individuales y corporativos en una tabla,
 * separadas por `affiliate_type`).
 *
 * - `affiliate_id` apunta a `affiliates` o a `affiliate_corporates` según el tipo; queda
 *   nulo si la cédula no corresponde a ningún afiliado (la encuesta se guarda igual).
 * - `dedupe_hash` = tipo + marca temporal + cédula normalizada: reimportar el mismo Excel
 *   no repite encuestas. Es único también entre las eliminadas, para que una encuesta
 *   borrada a propósito no vuelva a entrar con la siguiente importación.
 * - Eliminación lógica: un resultado de calidad no desaparece sin rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quality_control_surveys')) {
            return;
        }

        Schema::create('quality_control_surveys', function (Blueprint $table): void {
            $table->id();
            $table->string('affiliate_type', 20);
            $table->unsignedBigInteger('affiliate_id')->nullable();
            $table->dateTime('responded_at');
            $table->date('service_date')->nullable();
            $table->string('patient_document', 60);
            $table->string('patient_document_key', 40)->nullable();
            $table->string('patient_name');
            $table->string('contact_phone', 60)->nullable();
            $table->string('rating_channels', 30)->nullable();
            $table->string('rating_response_time', 30)->nullable();
            $table->string('rating_medical_service', 30)->nullable();
            $table->string('rating_overall', 30)->nullable();
            $table->text('observations')->nullable();
            $table->json('services')->nullable();
            $table->string('source', 20)->default('FORMULARIO');
            $table->uuid('import_batch')->nullable();
            $table->string('dedupe_hash', 64)->unique();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['affiliate_type', 'responded_at'], 'qcs_type_responded_idx');
            $table->index(['affiliate_type', 'service_date'], 'qcs_type_service_date_idx');
            $table->index(['affiliate_type', 'affiliate_id'], 'qcs_type_affiliate_idx');
            $table->index('patient_document_key', 'qcs_document_key_idx');
            $table->index('import_batch', 'qcs_import_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_control_surveys');
    }
};
