<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de cambios (Administración): cada alta, modificación, eliminación, restauración o
 * reversión de afiliaciones, cotizaciones, agencias, agentes, ventas, cobranza, comisiones,
 * planes, tarifas y Nuevos Negocios, con los valores de antes y después de cada campo.
 *
 * Idempotente: se puede aplicar con --path aunque la tabla ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_change_audits')) {
            return;
        }

        Schema::create('data_change_audits', function (Blueprint $table): void {
            $table->id();
            $table->char('batch_id', 36);
            $table->string('entity', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_label')->nullable();
            $table->string('parent_label')->nullable();
            $table->string('event', 20);
            $table->json('changes');
            $table->boolean('affects_calculations')->default(false);
            $table->string('fields', 1000)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('source', 20);
            $table->string('panel', 40)->nullable();
            $table->string('origin')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedBigInteger('reverts_audit_id')->nullable();
            $table->text('revert_reason')->nullable();
            $table->unsignedBigInteger('reverted_by_audit_id')->nullable();
            $table->timestamp('created_at', 6)->nullable();

            $table->index(['entity', 'entity_id', 'id'], 'dca_entity_idx');
            $table->index(['created_at'], 'dca_created_idx');
            $table->index(['user_id', 'created_at'], 'dca_user_idx');
            $table->index(['affects_calculations', 'created_at'], 'dca_critical_idx');
            $table->index(['entity_label'], 'dca_label_idx');
            $table->index(['parent_label'], 'dca_parent_idx');
            $table->index(['batch_id'], 'dca_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_change_audits');
    }
};
