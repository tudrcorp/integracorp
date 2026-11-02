<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades de una cotización corporativa. Misma forma que `individual_quote_upgrades`: copia
 * congelada del catálogo al cotizar y resultado frente a los rangos de edad y la población. No
 * toca las líneas del plan (`detail_corporate_quotes`).
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('corporate_quote_upgrades')) {
            return;
        }

        Schema::create('corporate_quote_upgrades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('corporate_quote_id');
            $table->string('line_key', 36);
            $table->unsignedBigInteger('upgrade_id')->nullable();
            $table->unsignedBigInteger('upgrade_coverage_id')->nullable();
            $table->string('upgrade_code', 40)->nullable();
            $table->string('upgrade_name', 120);
            $table->decimal('coverage_amount', 12, 2)->nullable();
            $table->json('age_prices');
            $table->json('services')->nullable();
            $table->json('excluded_ranges')->nullable();
            $table->json('manual_ranges')->nullable();
            $table->json('rows')->nullable();
            $table->unsignedInteger('persons')->default(0);
            $table->decimal('annual_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['corporate_quote_id', 'line_key'], 'cq_upgrades_quote_line_unique');
            $table->index('corporate_quote_id', 'cq_upgrades_quote_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_quote_upgrades');
    }
};
