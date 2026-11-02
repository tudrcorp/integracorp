<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades que también se venden como plan propio: el check `is_plan` y la unidad de negocio que
 * pide el plan. El plan real queda enlazado por `plans.source_upgrade_id`, que es además lo que
 * lo bloquea para que solo se edite desde el upgrade.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('upgrades', 'is_plan')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->boolean('is_plan')->default(false)->after('network_visibility');
                $table->unsignedBigInteger('plan_business_unit_id')->nullable()->after('is_plan');
            });
        }

        if (! Schema::hasColumn('plans', 'source_upgrade_id')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_upgrade_id')->nullable()->unique('plans_source_upgrade_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'source_upgrade_id')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropUnique('plans_source_upgrade_unique');
                $table->dropColumn('source_upgrade_id');
            });
        }

        if (Schema::hasColumn('upgrades', 'is_plan')) {
            Schema::table('upgrades', function (Blueprint $table): void {
                $table->dropColumn(['is_plan', 'plan_business_unit_id']);
            });
        }
    }
};
