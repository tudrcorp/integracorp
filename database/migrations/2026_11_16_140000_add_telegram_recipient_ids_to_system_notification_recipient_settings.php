<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas de Telegram elegidas a mano para un aviso del Centro de notificaciones,
 * además de los contactos del aviso que ya estén vinculados (como los grupos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_notification_recipient_settings', 'telegram_recipient_ids')) {
            Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
                $table->json('telegram_recipient_ids')->nullable()->after('telegram_group_ids');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_notification_recipient_settings', 'telegram_recipient_ids')) {
            Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
                $table->dropColumn('telegram_recipient_ids');
            });
        }
    }
};
