<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el analista hizo con cada sugerencia del copiloto: la usó o dijo que no aplica.
 * Sirve para no repetirla en el mismo caso y para medir cuáles ayudan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            if (! Schema::hasColumn('crm_handoff_envelopes', 'copilot_feedback')) {
                $table->json('copilot_feedback')->nullable()->after('last_customer_text');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_handoff_envelopes')) {
            return;
        }

        Schema::table('crm_handoff_envelopes', function (Blueprint $table): void {
            if (Schema::hasColumn('crm_handoff_envelopes', 'copilot_feedback')) {
                $table->dropColumn('copilot_feedback');
            }
        });
    }
};
