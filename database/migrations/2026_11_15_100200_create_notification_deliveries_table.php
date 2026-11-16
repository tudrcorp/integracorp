<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de envíos de notificaciones internas. Cada fila se escribe ANTES de
 * enviar (si el servidor cae a mitad, el aviso no se pierde) y registra por qué
 * canal salió, los intentos y el error. Los respaldos (WhatsApp / correo) quedan
 * como filas hijas de la entrega por Telegram que falló.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_deliveries')) {
            return;
        }

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('notification_deliveries')->nullOnDelete();
            $table->string('notification_key', 80);
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->foreignId('telegram_recipient_id')->nullable()->constrained('telegram_recipients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target', 255)->nullable();
            $table->string('target_label', 255)->nullable();
            $table->string('title', 255);
            $table->text('body');
            $table->boolean('is_critical')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id', 80)->nullable();
            $table->string('error', 1000)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['notification_key', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
