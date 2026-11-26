<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un caso sin tomar se puede marcar para un compañero o pasar a otro equipo.
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
            if (! Schema::hasColumn('crm_handoff_envelopes', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('closed_by');
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'assigned_by')) {
                $table->unsignedBigInteger('assigned_by')->nullable()->after('assigned_to');
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'moved_by')) {
                $table->unsignedBigInteger('moved_by')->nullable()->after('assigned_at');
            }

            if (! Schema::hasColumn('crm_handoff_envelopes', 'moved_at')) {
                $table->timestamp('moved_at')->nullable()->after('moved_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            foreach (['moved_at', 'moved_by', 'assigned_at', 'assigned_by', 'assigned_to'] as $column) {
                if (Schema::hasColumn('crm_handoff_envelopes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
