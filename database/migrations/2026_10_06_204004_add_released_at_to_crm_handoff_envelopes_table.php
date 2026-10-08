<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El caso sale de la cola cuando el analista devuelve la palabra al bot.
 * No toca afiliaciones ni la tabla de trabajos del resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        if (Schema::hasColumn('crm_handoff_envelopes', 'released_at')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            $table->timestamp('released_at')->nullable()->after('taken_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        if (! Schema::hasColumn('crm_handoff_envelopes', 'released_at')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            $table->dropColumn('released_at');
        });
    }
};
