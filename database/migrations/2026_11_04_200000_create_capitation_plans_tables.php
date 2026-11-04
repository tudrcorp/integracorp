<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes de capitado de los proveedores de la red Outsourcing (Negocios → Configuración).
 *
 * - `provider_type` + `provider_id`: proveedor jurídico (`supplier`) o natural (`doctor_nurse`).
 * - `documents` / `document_names`: respaldos de la negociación en el disco privado, con su
 *   nombre original.
 * - `capitation_plan_benefits`: beneficios del plan, del catálogo (`benefit_id`) o texto libre;
 *   el nombre se guarda siempre, para que el plan no cambie si el catálogo cambia.
 * - Eliminación lógica: un plan negociado no desaparece sin rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('capitation_plans')) {
            Schema::create('capitation_plans', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_type', 20);
                $table->unsignedBigInteger('provider_id');
                $table->string('name', 150);
                $table->decimal('cost_per_affiliate_usd', 12, 2);
                $table->string('payment_frequency', 20);
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->string('status', 20)->default('ACTIVO');
                $table->text('negotiation_type');
                $table->json('documents')->nullable();
                $table->json('document_names')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['provider_type', 'provider_id', 'status'], 'cap_plans_provider_status_idx');
                $table->index('name', 'cap_plans_name_idx');
            });
        }

        if (! Schema::hasTable('capitation_plan_benefits')) {
            Schema::create('capitation_plan_benefits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('capitation_plan_id')->constrained('capitation_plans')->cascadeOnDelete();
                $table->unsignedBigInteger('benefit_id')->nullable();
                $table->string('name');
                $table->string('note')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['capitation_plan_id', 'sort'], 'cap_plan_benefits_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_plan_benefits');
        Schema::dropIfExists('capitation_plans');
    }
};
