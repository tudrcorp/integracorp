<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un caso sin tomar puede salir de la bandeja sin avisar al cliente.
 * No toca afiliaciones ni la tabla de trabajos del resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_envelopes', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('released_at');
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'closed_by')) {
                $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            if (Schema::hasColumn('crm_handoff_envelopes', 'closed_by')) {
                $table->dropColumn('closed_by');
            }

            if (Schema::hasColumn('crm_handoff_envelopes', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
