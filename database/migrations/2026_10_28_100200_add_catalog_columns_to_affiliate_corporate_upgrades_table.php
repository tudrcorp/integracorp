<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga los upgrades corporativos al catálogo nuevo. Aditiva: `upgrade_benefit_id`
 * se conserva como enlace histórico al catálogo viejo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_corporate_upgrades')) {
            return;
        }

        Schema::table('affiliate_corporate_upgrades', function (Blueprint $table): void {
            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'upgrade_id')) {
                $table->foreignId('upgrade_id')->nullable()->after('upgrade_benefit_id')
                    ->constrained('upgrades', 'id', 'aff_corp_upgrades_upgrade_fk')->restrictOnDelete();
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'upgrade_coverage_id')) {
                $table->foreignId('upgrade_coverage_id')->nullable()->after('upgrade_id')
                    ->constrained('upgrade_coverages', 'id', 'aff_corp_upgrades_coverage_fk')->restrictOnDelete();
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'upgrade_coverage_age_range_id')) {
                $table->foreignId('upgrade_coverage_age_range_id')->nullable()->after('upgrade_coverage_id')
                    ->constrained('upgrade_coverage_age_ranges', 'id', 'aff_corp_upgrades_range_fk')->restrictOnDelete();
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'coverage_amount')) {
                $table->decimal('coverage_amount', 12, 2)->nullable()->after('name');
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'age_at_assignment')) {
                $table->unsignedTinyInteger('age_at_assignment')->nullable()->after('coverage_amount');
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'replaces_id')) {
                $table->unsignedBigInteger('replaces_id')->nullable()->after('status')->index('aff_corp_upgrades_replaces_idx');
            }

            if (! Schema::hasColumn('affiliate_corporate_upgrades', 'deactivation_reason')) {
                $table->string('deactivation_reason', 60)->nullable()->after('deactivated_by');
            }
        });

        Schema::table('affiliate_corporate_upgrades', function (Blueprint $table): void {
            if (! Schema::hasIndex('affiliate_corporate_upgrades', 'aff_corp_upgrades_upgrade_status_idx')) {
                $table->index(['upgrade_id', 'status'], 'aff_corp_upgrades_upgrade_status_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_corporate_upgrades')) {
            return;
        }

        Schema::table('affiliate_corporate_upgrades', function (Blueprint $table): void {
            if (Schema::hasIndex('affiliate_corporate_upgrades', 'aff_corp_upgrades_upgrade_status_idx')) {
                $table->dropIndex('aff_corp_upgrades_upgrade_status_idx');
            }

            foreach (['upgrade_coverage_age_range_id' => 'aff_corp_upgrades_range_fk', 'upgrade_coverage_id' => 'aff_corp_upgrades_coverage_fk', 'upgrade_id' => 'aff_corp_upgrades_upgrade_fk'] as $column => $foreign) {
                if (Schema::hasColumn('affiliate_corporate_upgrades', $column)) {
                    $table->dropForeign($foreign);
                    $table->dropColumn($column);
                }
            }

            foreach (['coverage_amount', 'age_at_assignment', 'replaces_id', 'deactivation_reason'] as $column) {
                if (Schema::hasColumn('affiliate_corporate_upgrades', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
