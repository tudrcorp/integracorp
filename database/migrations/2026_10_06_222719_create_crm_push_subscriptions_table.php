<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones de aviso del navegador. Una fila por navegador, del analista
 * que pulsó Activar avisos. No guarda mensajes ni toca la cola de trabajos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_push_subscriptions')) {
            return;
        }

        Schema::create('crm_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 32)->default('aes128gcm');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_push_subscriptions');
    }
};
