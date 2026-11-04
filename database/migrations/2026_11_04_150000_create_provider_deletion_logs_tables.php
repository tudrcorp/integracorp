<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de eliminaciones de proveedores, para poder deshacerlas.
 *
 * - `provider_deletion_logs`: una fila por eliminación (quién, cuándo, motivo) y, si se
 *   restauró, quién, cuándo, motivo y resumen de lo que volvió.
 * - `provider_deletion_log_items`: una fila por orden o cita que se reasignó o canceló al
 *   eliminar, con la «foto» de cómo estaba antes (`snapshot`) y lo que se hizo (`result`).
 *   Al restaurar se marca si volvió o por qué no pudo volver.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_deletion_logs')) {
            Schema::create('provider_deletion_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_type', 30);
                $table->unsignedBigInteger('provider_id');
                $table->string('provider_name');
                $table->text('reason');
                $table->unsignedBigInteger('deleted_by_user_id')->nullable();
                $table->string('deleted_by_name')->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->unsignedBigInteger('restored_by_user_id')->nullable();
                $table->string('restored_by_name')->nullable();
                $table->text('restoration_reason')->nullable();
                $table->json('restoration_summary')->nullable();
                $table->timestamps();

                $table->index(['provider_type', 'provider_id', 'restored_at'], 'pdl_provider_restored_idx');
            });
        }

        if (! Schema::hasTable('provider_deletion_log_items')) {
            Schema::create('provider_deletion_log_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('provider_deletion_log_id')->constrained('provider_deletion_logs')->cascadeOnDelete();
                $table->string('kind', 20);
                $table->string('action', 20);
                $table->unsignedBigInteger('operation_service_order_id')->nullable();
                $table->unsignedBigInteger('operation_medical_appointment_id')->nullable();
                $table->json('snapshot');
                $table->json('result')->nullable();
                $table->string('restore_status', 20)->nullable();
                $table->text('restore_note')->nullable();
                $table->timestamps();

                $table->index('operation_service_order_id', 'pdli_order_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_deletion_log_items');
        Schema::dropIfExists('provider_deletion_logs');
    }
};
