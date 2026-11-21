<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de notificaciones con control total:
 *
 * - notification_catalog_entries: cada tipo de mensaje que sale del sistema (interno o
 *   externo), con sus interruptores. Los envíos que no estén catalogados se agregan solos
 *   como «Sin clasificar» la primera vez que salen.
 * - notification_channel_settings: interruptor general de cada canal (WhatsApp, correo,
 *   Telegram). Los mensajes críticos (claves OTP, alertas) no obedecen el interruptor general.
 * - outbound_messages: bitácora de todo lo que sale por WhatsApp, correo y Telegram
 *   (90 días). Las campañas masivas y los cumpleaños no se registran: tienen su bitácora.
 *
 * Solo crea tablas nuevas: no toca ninguna existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_catalog_entries')) {
            Schema::create('notification_catalog_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('name', 160);
                $table->string('description', 500)->nullable();
                $table->string('area', 40)->default('sin-clasificar');
                $table->string('audience', 10)->default('external');
                $table->string('system_key', 80)->nullable();
                $table->json('origins')->nullable();
                $table->json('channels')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('whatsapp_enabled')->default(true);
                $table->boolean('mail_enabled')->default(true);
                $table->boolean('is_critical')->default(false);
                $table->boolean('is_bulk')->default(false);
                $table->boolean('is_discovered')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->index(['audience', 'area'], 'nce_audience_area_idx');
            });
        }

        if (! Schema::hasTable('notification_channel_settings')) {
            Schema::create('notification_channel_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('channel', 20)->unique();
                $table->boolean('is_enabled')->default(true);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('outbound_messages')) {
            Schema::create('outbound_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('channel', 12);
                $table->string('status', 12);
                $table->string('audience', 10);
                $table->string('catalog_key', 120)->nullable();
                $table->string('message_type', 20)->nullable();
                $table->string('recipient', 255)->nullable();
                $table->string('recipient_key', 64)->nullable();
                $table->string('recipient_name', 255)->nullable();
                $table->unsignedSmallInteger('recipients_count')->default(1);
                $table->string('subject', 255)->nullable();
                $table->text('body')->nullable();
                $table->string('attachment_name', 255)->nullable();
                $table->string('error', 1000)->nullable();
                $table->string('origin', 255)->nullable();
                $table->boolean('is_critical')->default(false);
                $table->timestamps();

                $table->index('created_at', 'om_created_at_idx');
                $table->index(['channel', 'created_at'], 'om_channel_created_idx');
                $table->index(['audience', 'created_at'], 'om_audience_created_idx');
                $table->index(['status', 'created_at'], 'om_status_created_idx');
                $table->index(['catalog_key', 'created_at'], 'om_catalog_created_idx');
                $table->index('recipient_key', 'om_recipient_key_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('notification_channel_settings');
        Schema::dropIfExists('notification_catalog_entries');
    }
};
