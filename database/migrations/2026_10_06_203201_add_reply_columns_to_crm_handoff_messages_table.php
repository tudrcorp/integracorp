<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La respuesta del analista y la nota interna viven en el mismo hilo.
 * No toca afiliaciones ni la tabla de trabajos del resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::table('crm_handoff_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_messages', 'direction')) {
                $table->string('direction', 8)->default('in');
            }

            if (! Schema::hasColumn('crm_handoff_messages', 'kind')) {
                $table->string('kind', 16)->default('message');
            }

            if (! Schema::hasColumn('crm_handoff_messages', 'status')) {
                $table->string('status', 16)->default('received');
            }

            if (! Schema::hasColumn('crm_handoff_messages', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }

            if (! Schema::hasColumn('crm_handoff_messages', 'provider_message_id')) {
                $table->string('provider_message_id', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::table('crm_handoff_messages', function (Blueprint $table): void {
            foreach (['provider_message_id', 'user_id', 'status', 'kind', 'direction'] as $column) {
                if (Schema::hasColumn('crm_handoff_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
