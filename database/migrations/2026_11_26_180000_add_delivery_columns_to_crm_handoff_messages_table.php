<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doble check en Atención WhatsApp: cuándo WhatsApp entregó cada respuesta del analista
 * (doble check gris) y cuándo el cliente la leyó (doble check azul). Llegan desde Evolution
 * por el bot y se buscan por el id de WhatsApp (`provider_message_id`), que ahora tiene índice.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    private const TABLE = 'crm_handoff_messages';

    private const INDEX = 'crm_msg_provider_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'delivered_at')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->timestamp('delivered_at')->nullable()->after('provider_message_id'));
        }

        if (! Schema::hasColumn(self::TABLE, 'read_at')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->timestamp('read_at')->nullable()->after('delivered_at'));
        }

        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->index('provider_message_id', self::INDEX));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }

        foreach (['read_at', 'delivered_at'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
