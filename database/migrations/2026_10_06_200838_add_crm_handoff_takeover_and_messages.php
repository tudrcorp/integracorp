<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El analista toma el caso y los mensajes siguientes del cliente se guardan
 * aparte. No toca afiliaciones ni la tabla de trabajos del resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_handoff_envelopes')) {
            Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
                if (! Schema::hasColumn('crm_handoff_envelopes', 'taken_at')) {
                    $table->timestamp('taken_at')->nullable()->after('accepted_at');
                }

                if (! Schema::hasColumn('crm_handoff_envelopes', 'taken_by')) {
                    $table->unsignedBigInteger('taken_by')->nullable()->after('taken_at');
                }

                if (! Schema::hasColumn('crm_handoff_envelopes', 'last_customer_at')) {
                    $table->timestamp('last_customer_at')->nullable()->after('taken_by');
                }

                if (! Schema::hasColumn('crm_handoff_envelopes', 'last_customer_text')) {
                    $table->string('last_customer_text', 200)->nullable()->after('last_customer_at');
                }
            });
        }

        if (Schema::hasTable('crm_handoff_messages')) {
            return;
        }

        Schema::create('crm_handoff_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 191)->unique();
            $table->string('handoff_id', 32)->nullable()->index();
            $table->string('phone', 20)->index();
            $table->text('body');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_handoff_messages');

        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            foreach (['last_customer_text', 'last_customer_at', 'taken_by', 'taken_at'] as $column) {
                if (Schema::hasColumn('crm_handoff_envelopes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
