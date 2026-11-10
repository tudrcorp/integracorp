<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan de capitado asignado directamente a un afiliado (individual, corporativo o Nuevos
 * Negocios). Reemplaza el cálculo automático (proveedor de la afiliación + plan TDEC cubierto).
 * Una asignación vigente por afiliado; el historial permite deshacer paso a paso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_capitation_assignments')) {
            Schema::create('affiliate_capitation_assignments', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 30);
                $table->unsignedBigInteger('subject_id');
                $table->foreignId('capitation_plan_id')->index('aca_plan_idx');
                $table->text('reason');
                $table->foreignId('created_by_user_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->timestamps();

                $table->unique(['subject_type', 'subject_id'], 'aca_subject_unique');
            });
        }

        if (! Schema::hasTable('affiliate_capitation_assignment_logs')) {
            Schema::create('affiliate_capitation_assignment_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type', 30);
                $table->unsignedBigInteger('subject_id');
                $table->string('action', 20);
                $table->foreignId('previous_capitation_plan_id')->nullable();
                $table->foreignId('new_capitation_plan_id')->nullable();
                $table->text('previous_reason')->nullable();
                $table->text('reason');
                $table->uuid('batch_uuid')->index('acal_batch_idx');
                $table->foreignId('reverts_log_id')->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->string('reverted_by_name')->nullable();
                $table->foreignId('created_by_user_id')->nullable();
                $table->string('created_by_name')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['subject_type', 'subject_id'], 'acal_subject_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_capitation_assignment_logs');
        Schema::dropIfExists('affiliate_capitation_assignments');
    }
};
