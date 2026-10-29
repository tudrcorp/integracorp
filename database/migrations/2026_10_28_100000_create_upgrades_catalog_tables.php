<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de upgrades: el upgrade, sus servicios, sus coberturas y los rangos de
 * edad de cada cobertura (donde vive el precio en US$).
 *
 * Nada de esto se borra en la operación normal: lo que sale del catálogo pasa a
 * INACTIVO, porque las asignaciones a afiliados apuntan a cobertura y rango.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('upgrades')) {
            Schema::create('upgrades', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('ACTIVO')->index();
                $table->unsignedBigInteger('legacy_upgrade_benefit_id')->nullable()->unique();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('upgrade_services')) {
            Schema::create('upgrade_services', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('upgrade_id')->constrained('upgrades', 'id', 'upg_services_upgrade_fk')->restrictOnDelete();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->string('status', 20)->default('ACTIVO');
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->index(['upgrade_id', 'status'], 'upg_services_upgrade_status_idx');
            });
        }

        if (! Schema::hasTable('upgrade_coverages')) {
            Schema::create('upgrade_coverages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('upgrade_id')->constrained('upgrades', 'id', 'upg_coverages_upgrade_fk')->restrictOnDelete();
                $table->decimal('amount', 12, 2)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->string('status', 20)->default('ACTIVO');
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->index(['upgrade_id', 'status'], 'upg_coverages_upgrade_status_idx');
            });
        }

        if (! Schema::hasTable('upgrade_coverage_age_ranges')) {
            Schema::create('upgrade_coverage_age_ranges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('upgrade_coverage_id')->constrained('upgrade_coverages', 'id', 'upg_ranges_coverage_fk')->restrictOnDelete();
                $table->unsignedTinyInteger('age_from');
                $table->unsignedTinyInteger('age_to');
                $table->decimal('price', 10, 2);
                $table->string('status', 20)->default('ACTIVO');
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->index(['upgrade_coverage_id', 'status', 'age_from'], 'upg_ranges_coverage_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_coverage_age_ranges');
        Schema::dropIfExists('upgrade_coverages');
        Schema::dropIfExists('upgrade_services');
        Schema::dropIfExists('upgrades');
    }
};
