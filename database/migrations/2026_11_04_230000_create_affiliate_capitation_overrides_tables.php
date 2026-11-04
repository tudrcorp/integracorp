<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monto de capitación negociado directamente para un afiliado (individual, corporativo o de
 * Nuevos Negocios), que reemplaza al del plan de capitado mientras ese plan le aplique.
 *
 * - `affiliate_capitation_overrides`: el ajuste vigente, uno por afiliado.
 * - `affiliate_capitation_override_logs`: historial de cada ajuste y de cada retiro, con razón.
 *
 * Idempotente: el esquema local viene de un dump y `migrate` a secas no corre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_capitation_overrides')) {
            Schema::create('affiliate_capitation_overrides', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 30);
                $table->unsignedBigInteger('subject_id');
                $table->decimal('amount_usd', 12, 2);
                $table->text('reason');
                $table->unsignedBigInteger('capitation_plan_id')->nullable();
                $table->decimal('plan_amount_usd', 12, 2)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->timestamps();

                $table->unique(['subject_type', 'subject_id'], 'aff_cap_override_subject_unique');
            });
        }

        if (! Schema::hasTable('affiliate_capitation_override_logs')) {
            Schema::create('affiliate_capitation_override_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 30);
                $table->unsignedBigInteger('subject_id');
                $table->string('action', 20);
                $table->decimal('previous_amount_usd', 12, 2)->nullable();
                $table->decimal('new_amount_usd', 12, 2)->nullable();
                $table->decimal('plan_amount_usd', 12, 2)->nullable();
                $table->unsignedBigInteger('capitation_plan_id')->nullable();
                $table->text('reason');
                $table->uuid('batch_uuid');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['subject_type', 'subject_id', 'id'], 'aff_cap_override_log_subject_idx');
                $table->index('batch_uuid', 'aff_cap_override_log_batch_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_capitation_override_logs');
        Schema::dropIfExists('affiliate_capitation_overrides');
    }
};
