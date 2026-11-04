<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes TDEC que cubre cada plan de capitado. Con esta relación se resuelve cuánto cuesta
 * en capitación un afiliado: proveedor Outsourcing de su afiliación + plan TDEC del afiliado.
 *
 * Idempotente: el esquema local viene de un dump y `migrate` a secas no corre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capitation_plan_covered_plans')) {
            return;
        }

        Schema::create('capitation_plan_covered_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('capitation_plan_id')->constrained('capitation_plans')->cascadeOnDelete();
            $table->unsignedBigInteger('plan_id');
            $table->timestamps();

            $table->unique(['capitation_plan_id', 'plan_id'], 'cap_covered_plan_unique');
            $table->index('plan_id', 'cap_covered_plan_plan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_plan_covered_plans');
    }
};
