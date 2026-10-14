<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intentos de envío de cada respuesta. El reintento automático se detiene al tercero.
 * El índice por estado sirve a ese reintento; el del hilo, a la cola y al historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::table('crm_handoff_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_messages', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0);
            }
        });

        if (! Schema::hasIndex('crm_handoff_messages', 'crm_msg_status_idx')) {
            Schema::table('crm_handoff_messages', function (Blueprint $table): void {
                $table->index(['status', 'received_at'], 'crm_msg_status_idx');
            });
        }

        if (! Schema::hasIndex('crm_handoff_messages', 'crm_msg_thread_idx')) {
            Schema::table('crm_handoff_messages', function (Blueprint $table): void {
                $table->index(['handoff_id', 'direction', 'status'], 'crm_msg_thread_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        foreach (['crm_msg_status_idx', 'crm_msg_thread_idx'] as $name) {
            if (Schema::hasIndex('crm_handoff_messages', $name)) {
                Schema::table('crm_handoff_messages', function (Blueprint $table) use ($name): void {
                    $table->dropIndex($name);
                });
            }
        }

        if (Schema::hasColumn('crm_handoff_messages', 'attempts')) {
            Schema::table('crm_handoff_messages', function (Blueprint $table): void {
                $table->dropColumn('attempts');
            });
        }
    }
};
