<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces de vinculación de un solo uso (t.me/<bot>?start=<código>).
 * Solo se guarda el hash SHA-256 del código: quien lea la tabla no puede vincularse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telegram_link_tokens')) {
            return;
        }

        Schema::create('telegram_link_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_recipient_id')->constrained('telegram_recipients')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_chat_id', 40)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['telegram_recipient_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
    }
};
