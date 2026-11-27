<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivos que el cliente envía por WhatsApp al CRM de atención (imágenes, documentos y notas de voz).
 * Uno por mensaje de WhatsApp: `message_id` es único y hace idempotente el reintento del bot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_handoff_attachments')) {
            return;
        }

        Schema::create('crm_handoff_attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 191)->unique();
            $table->unsignedBigInteger('crm_handoff_message_id')->nullable()->index();
            $table->string('handoff_id', 32)->nullable()->index();
            $table->string('phone', 20)->index();
            $table->string('media_type', 20);
            $table->string('mime_type', 120);
            $table->string('original_name', 191);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('caption', 1000)->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable()->index();
            $table->unsignedBigInteger('telemedicine_case_attachment_id')->nullable();
            $table->timestamp('filed_at')->nullable();
            $table->unsignedBigInteger('filed_by')->nullable();
            $table->boolean('filing_skipped')->default(false);
            $table->timestamp('purged_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_handoff_attachments');
    }
};
