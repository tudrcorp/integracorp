<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades incluidos en una cotización del generador de planes.
 *
 * Cada fila guarda una copia congelada de lo que se cotizó (nombre, cobertura,
 * precios por edad y servicios): cambiar el catálogo después no mueve una
 * cotización ya emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_generator_upgrades')) {
            return;
        }

        Schema::create('plan_generator_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_generator_id')->constrained('plan_generators', 'id', 'pg_upgrades_generator_fk')->cascadeOnDelete();
            $table->string('line_key', 36);
            $table->foreignId('upgrade_id')->nullable()->constrained('upgrades', 'id', 'pg_upgrades_upgrade_fk')->nullOnDelete();
            $table->foreignId('upgrade_coverage_id')->nullable()->constrained('upgrade_coverages', 'id', 'pg_upgrades_coverage_fk')->nullOnDelete();
            $table->string('upgrade_code', 40)->nullable();
            $table->string('upgrade_name', 120);
            $table->decimal('coverage_amount', 12, 2)->nullable();
            $table->json('age_prices');
            $table->json('services')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['plan_generator_id', 'line_key'], 'pg_upgrades_generator_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_generator_upgrades');
    }
};
