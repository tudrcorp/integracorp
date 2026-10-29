<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de edad que el upgrade no cubre y el analista incluyó a mano, con su
 * precio, la nota obligatoria del porqué, quién y cuándo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_generator_upgrades') || Schema::hasColumn('plan_generator_upgrades', 'manual_ranges')) {
            return;
        }

        Schema::table('plan_generator_upgrades', function (Blueprint $table): void {
            $table->json('manual_ranges')->nullable()->after('excluded_ranges');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_generator_upgrades') && Schema::hasColumn('plan_generator_upgrades', 'manual_ranges')) {
            Schema::table('plan_generator_upgrades', function (Blueprint $table): void {
                $table->dropColumn('manual_ranges');
            });
        }
    }
};
