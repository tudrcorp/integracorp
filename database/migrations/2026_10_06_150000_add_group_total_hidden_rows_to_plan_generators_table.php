<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filas del total grupal que el analista quitó (anual, semestral, trimestral).
 * Nulo = se muestran todas, como hasta ahora. La mensual sigue gobernada por
 * `include_monthly_total`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('plan_generators', 'group_total_hidden_rows')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->json('group_total_hidden_rows')->nullable()->after('include_monthly_total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plan_generators', 'group_total_hidden_rows')) {
            return;
        }

        Schema::table('plan_generators', function (Blueprint $table): void {
            $table->dropColumn('group_total_hidden_rows');
        });
    }
};
