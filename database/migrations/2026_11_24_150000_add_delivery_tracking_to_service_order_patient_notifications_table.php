<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega del WhatsApp al paciente: los checks de WhatsApp (✓ enviado, ✓✓ entregado,
 * ✓✓ azul leído) que UltraMsg avisa por el webhook (`message_ack`) o que se consultan.
 * Así el analista sabe si el mensaje llegó al teléfono aunque el paciente no responda.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    private const TABLE = 'service_order_patient_notifications';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = [
            'delivery_status' => fn (Blueprint $table) => $table->string('delivery_status', 20)->nullable()->after('ultramsg_message_id'),
            'whatsapp_message_id' => fn (Blueprint $table) => $table->string('whatsapp_message_id', 191)->nullable()->after('delivery_status'),
            'delivered_at' => fn (Blueprint $table) => $table->timestamp('delivered_at')->nullable()->after('sent_at'),
            'read_at' => fn (Blueprint $table) => $table->timestamp('read_at')->nullable()->after('delivered_at'),
            'delivery_checked_at' => fn (Blueprint $table) => $table->timestamp('delivery_checked_at')->nullable()->after('read_at'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $definition($table));
            }
        }

        $indexes = [
            'sopn_ultramsg_id_index' => ['ultramsg_message_id'],
            'sopn_whatsapp_id_index' => ['whatsapp_message_id'],
        ];

        foreach ($indexes as $name => $indexColumns) {
            if (! Schema::hasIndex(self::TABLE, $name)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->index($indexColumns, $name));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (['sopn_ultramsg_id_index', 'sopn_whatsapp_id_index'] as $name) {
            if (Schema::hasIndex(self::TABLE, $name)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex($name));
            }
        }

        foreach (['delivery_status', 'whatsapp_message_id', 'delivered_at', 'read_at', 'delivery_checked_at'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
