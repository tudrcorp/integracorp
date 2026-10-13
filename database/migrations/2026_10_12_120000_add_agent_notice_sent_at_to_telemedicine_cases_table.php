<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca del aviso por WhatsApp al agente (o agencia) dueño de la afiliación
 * cuando se registra el caso. El job la reclama con un UPDATE atómico antes de
 * enviar: así el aviso sale una sola vez aunque el job se reintente o se
 * dispare dos veces. Los seguimientos del caso no la tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telemedicine_cases') || Schema::hasColumn('telemedicine_cases', 'agent_notice_sent_at')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            $table->timestamp('agent_notice_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telemedicine_cases') || ! Schema::hasColumn('telemedicine_cases', 'agent_notice_sent_at')) {
            return;
        }

        Schema::table('telemedicine_cases', function (Blueprint $table): void {
            $table->dropColumn('agent_notice_sent_at');
        });
    }
};
