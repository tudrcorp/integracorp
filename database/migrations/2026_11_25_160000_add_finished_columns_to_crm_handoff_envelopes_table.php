<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atención WhatsApp: conversación finalizada por el analista (distinta de «devuelta al bot»).
 * El bot confirma el cierre y el próximo mensaje del cliente abre una conversación nueva.
 * Base de las estadísticas de conversaciones atendidas y finalizadas por analista.
 *
 * Aditiva e idempotente (aplicar con --path).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        if (! Schema::hasColumn('crm_handoff_envelopes', 'finished_at')) {
            Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
                $table->timestamp('finished_at')->nullable()->after('closed_by');
            });
        }

        if (! Schema::hasColumn('crm_handoff_envelopes', 'finished_by')) {
            Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
                $table->unsignedBigInteger('finished_by')->nullable()->after('finished_at');
            });
        }

        if (! collect(Schema::getIndexes('crm_handoff_envelopes'))->contains(fn (array $index): bool => $index['name'] === 'crm_env_finished_idx')) {
            Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
                $table->index(['finished_by', 'finished_at'], 'crm_env_finished_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        if (collect(Schema::getIndexes('crm_handoff_envelopes'))->contains(fn (array $index): bool => $index['name'] === 'crm_env_finished_idx')) {
            Schema::table('crm_handoff_envelopes', fn (Blueprint $table) => $table->dropIndex('crm_env_finished_idx'));
        }

        foreach (['finished_by', 'finished_at'] as $column) {
            if (Schema::hasColumn('crm_handoff_envelopes', $column)) {
                Schema::table('crm_handoff_envelopes', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
