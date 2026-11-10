<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agente y agencia de la cotización borrada: sin ellos, una borrada no contaría en la
 * efectividad de la fuerza de ventas. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quote_deletion_logs')) {
            return;
        }

        Schema::table('quote_deletion_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('quote_deletion_logs', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('quote_deletion_logs', 'code_agency')) {
                $table->string('code_agency')->nullable()->after('agent_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quote_deletion_logs')) {
            return;
        }

        Schema::table('quote_deletion_logs', function (Blueprint $table): void {
            foreach (['code_agency', 'agent_id'] as $column) {
                if (Schema::hasColumn('quote_deletion_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
