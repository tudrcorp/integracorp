<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo de copia de cada correo testigo (CC visible o CCO oculta) en el Centro de notificaciones.
 * Mapa correo → modo; un correo sin entrada se toma como CC, que es como funcionaba antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('system_notification_recipient_settings', 'notification_email_modes')) {
            Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
                $table->json('notification_email_modes')->nullable()->after('notification_emails');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_notification_recipient_settings', 'notification_email_modes')) {
            Schema::table('system_notification_recipient_settings', function (Blueprint $table): void {
                $table->dropColumn('notification_email_modes');
            });
        }
    }
};
