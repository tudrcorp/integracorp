<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destinos de Telegram para notificaciones internas: personal @tudrencasa.com,
 * contactos externos que agrega un SUPERADMIN y grupos aprobados.
 * El chat_id lo entrega Telegram al vincular; antes de eso queda nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telegram_recipients')) {
            return;
        }

        Schema::create('telegram_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('chat_id', 40)->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('chat_id');
            $table->index(['type', 'status']);
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_recipients');
    }
};
