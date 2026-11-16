<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código corto de 6 dígitos para vincular Telegram sin escanear el QR: la persona
 * le escribe el código al bot. Se guarda como HMAC con la clave de la aplicación,
 * nunca en claro, y vence junto con el enlace.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('telegram_link_tokens', 'short_code_hash')) {
            Schema::table('telegram_link_tokens', function (Blueprint $table): void {
                $table->char('short_code_hash', 64)->nullable()->after('token_hash');
                $table->index(['short_code_hash', 'used_at', 'expires_at'], 'telegram_link_tokens_short_code_lookup');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('telegram_link_tokens', 'short_code_hash')) {
            Schema::table('telegram_link_tokens', function (Blueprint $table): void {
                $table->dropIndex('telegram_link_tokens_short_code_lookup');
                $table->dropColumn('short_code_hash');
            });
        }
    }
};
