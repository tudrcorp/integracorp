<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza del flujo cotización → orden de servicio de Operaciones.
 *
 * Una fila por hecho (orden creada, cotización aprobada, orden anulada, cotización
 * liberada, intento bloqueado…), con quién, cuándo, motivo y el antes/después.
 * Es solo de escritura: nada la actualiza ni la borra.
 *
 * Idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_service_flow_events')) {
            return;
        }

        Schema::create('operation_service_flow_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->unsignedBigInteger('operation_service_order_id')->nullable();
            $table->unsignedBigInteger('operation_quote_generator_id')->nullable();
            $table->string('event', 60);
            $table->text('description');
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['operation_coordination_service_id', 'id'], 'osfe_coordination_idx');
            $table->index(['operation_service_order_id', 'id'], 'osfe_order_idx');
            $table->index(['operation_quote_generator_id', 'id'], 'osfe_quote_idx');
            $table->index(['telemedicine_case_id', 'id'], 'osfe_case_idx');
            $table->index(['event', 'created_at'], 'osfe_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_service_flow_events');
    }
};
