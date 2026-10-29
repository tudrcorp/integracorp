<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de edad de la cotización que el analista sacó del upgrade. Se guardan
 * los excluidos, no los incluidos: un rango nuevo con precio entra solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generator_upgrades') || Schema::hasColumn('plan_generator_upgrades', 'excluded_ranges')) {
            return;
        }

        Schema::table('plan_generator_upgrades', function (Blueprint $table): void {
            $table->json('excluded_ranges')->nullable()->after('services');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_generator_upgrades') && Schema::hasColumn('plan_generator_upgrades', 'excluded_ranges')) {
            Schema::table('plan_generator_upgrades', function (Blueprint $table): void {
                $table->dropColumn('excluded_ranges');
            });
        }
    }
};
