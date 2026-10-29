<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades asignados a afiliados individuales. La afiliación no se guarda aquí:
 * se toma de `affiliates.affiliation_id`, así una unificación de grupo familiar
 * que mueve al afiliado no deja el upgrade colgado de la afiliación vieja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_upgrades')) {
            return;
        }

        Schema::create('affiliate_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates', 'id', 'aff_upgrades_affiliate_fk')->cascadeOnDelete();
            $table->foreignId('upgrade_id')->constrained('upgrades', 'id', 'aff_upgrades_upgrade_fk')->restrictOnDelete();
            $table->foreignId('upgrade_coverage_id')->constrained('upgrade_coverages', 'id', 'aff_upgrades_coverage_fk')->restrictOnDelete();
            $table->foreignId('upgrade_coverage_age_range_id')->nullable()->constrained('upgrade_coverage_age_ranges', 'id', 'aff_upgrades_range_fk')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('coverage_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('age_at_assignment')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('ACTIVO');
            $table->unsignedBigInteger('replaces_id')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivated_by')->nullable();
            $table->string('deactivation_reason', 60)->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status'], 'aff_upgrades_affiliate_status_idx');
            $table->index(['upgrade_id', 'status'], 'aff_upgrades_upgrade_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_upgrades');
    }
};
