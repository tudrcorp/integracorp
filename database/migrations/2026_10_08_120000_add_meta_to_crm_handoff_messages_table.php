<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la propuesta enviada (plan, personas, cobertura) para mostrarla como tarjeta en el hilo.
 * El texto que recibe el cliente no cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::table('crm_handoff_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_messages', 'meta')) {
                $table->json('meta')->nullable()->after('provider_message_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::table('crm_handoff_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('crm_handoff_messages', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
