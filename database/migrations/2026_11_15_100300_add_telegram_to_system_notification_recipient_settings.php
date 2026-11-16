<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram en el Centro de notificaciones: a quién va cada aviso (apagado,
 * persona, grupo o ambos) y los grupos aprobados elegidos. «off» por defecto:
 * ningún aviso existente cambia de canal hasta que alguien lo configure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_notification_recipient_settings', 'telegram_mode')) {
                $table->string('telegram_mode', 10)->default('off')->after('notification_phones');
            }

            if (! Schema::hasColumn('system_notification_recipient_settings', 'telegram_group_ids')) {
                $table->json('telegram_group_ids')->nullable()->after('notification_phones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
            foreach (['telegram_mode', 'telegram_group_ids'] as $column) {
                if (Schema::hasColumn('system_notification_recipient_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
