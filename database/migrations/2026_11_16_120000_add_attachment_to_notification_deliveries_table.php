<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento adjunto de una notificación interna (por ejemplo, el PDF de las copias
 * testigo de recibos y avisos de cobro). La ruta sirve para subirlo a Telegram; la
 * URL pública, para el respaldo por WhatsApp si Telegram no lo entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_deliveries', 'attachment_path')) {
                $table->string('attachment_path', 500)->nullable()->after('body');
            }

            if (! Schema::hasColumn('notification_deliveries', 'attachment_name')) {
                $table->string('attachment_name', 255)->nullable()->after('body');
            }

            if (! Schema::hasColumn('notification_deliveries', 'attachment_url')) {
                $table->string('attachment_url', 1000)->nullable()->after('body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            foreach (['attachment_path', 'attachment_name', 'attachment_url'] as $column) {
                if (Schema::hasColumn('notification_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
